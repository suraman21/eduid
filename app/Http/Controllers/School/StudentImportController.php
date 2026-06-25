<?php

namespace App\Http\Controllers\School;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\GuardianService;
use App\Services\QrTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * CSV bulk student import.
 *
 * Flow (validate-all-then-import):
 *   GET  /school/students/import          – upload form + template download
 *   POST /school/students/import          – validate CSV, store tmp, show preview
 *   POST /school/students/import/confirm  – import valid rows from stored tmp file
 *
 * The tmp CSV is stored in storage/app/imports/ with a session-keyed name so
 * the confirm step re-reads exactly the file that was previewed. Files are
 * small text so no queue workers needed (cPanel-safe).
 */
class StudentImportController extends BaseSchoolController
{
    // Accepted CSV column headers (case-insensitive, trimmed).
    // grade + section identify the class (PRD §08). The app keys classes by
    // grade+section ("Grade 9" + "A"), not a single combined name.
    private const COLUMNS = [
        'first_name', 'last_name', 'first_name_am', 'last_name_am',
        'gender', 'date_of_birth', 'grade', 'section', 'blood_type', 'status',
        'medical_notes', 'guardian_name', 'guardian_phone', 'guardian_relationship',
    ];

    private const REQUIRED = ['first_name', 'last_name', 'gender'];

    private const GENDERS       = ['male', 'female'];
    private const STATUSES      = ['active', 'promoted', 'repeating', 'graduated', 'transferred', 'withdrawn'];
    private const BLOOD_TYPES   = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    private const RELATIONSHIPS = ['father', 'mother', 'guardian', 'sibling', 'other'];

    public function __construct(
        private readonly QrTokenService  $qr,
        private readonly GuardianService $guardians,
    ) {}

    public function create()
    {
        $this->requireAdmin();
        $this->requireFeature();

        return Inertia::render('School/Students/Import', [
            'classes' => SchoolClass::where('is_active', true)->orderBy('grade')->get(['id', 'grade', 'section']),
        ]);
    }

    /**
     * Validate the uploaded CSV and store a tmp copy for the confirm step.
     */
    public function preview(Request $request)
    {
        $this->requireAdmin();
        $this->requireFeature();

        $request->validate(['csv_file' => 'required|file|mimes:csv,txt|max:2048']);

        $path = $request->file('csv_file')->getRealPath();
        $rows = $this->parseCSV($path);

        if (empty($rows)) {
            return back()->with('error', 'The CSV file is empty or has no valid rows.');
        }

        // Validate every row, collecting errors per row.
        $classes = SchoolClass::where('is_active', true)->get()->keyBy(fn ($c) => $this->classKey($c->grade, $c->section));
        $results = [];

        foreach ($rows as $i => $row) {
            $errors = $this->validateRow($row, $i + 2, $classes); // +2: header + 1-based
            $results[] = [
                'row'    => $i + 2,
                'data'   => $row,
                'errors' => $errors,
                'valid'  => empty($errors),
            ];
        }

        $validCount   = count(array_filter($results, fn ($r) => $r['valid']));
        $invalidCount = count($results) - $validCount;

        if ($validCount === 0) {
            return back()->with('error', 'No valid rows found. Please fix the errors and re-upload.');
        }

        // Store the tmp file so confirm can re-read it (avoid re-uploading).
        $tmpKey  = Str::random(40);
        $tmpPath = "imports/{$tmpKey}.csv";
        Storage::put($tmpPath, file_get_contents($path));
        session(['csv_import_tmp' => $tmpPath, 'csv_import_key' => $tmpKey]);

        return Inertia::render('School/Students/Import', [
            'classes'      => SchoolClass::where('is_active', true)->orderBy('grade')->get(['id', 'grade', 'section']),
            'preview'      => $results,
            'validCount'   => $validCount,
            'invalidCount' => $invalidCount,
            'tmpKey'       => $tmpKey,
        ]);
    }

    /**
     * Import only the valid rows from the stored tmp CSV.
     */
    public function confirm(Request $request)
    {
        $this->requireAdmin();
        $this->requireFeature();
        $this->requireWritableSubscription();

        $request->validate(['tmp_key' => 'required|string|size:40']);

        $tmpKey  = $request->input('tmp_key');
        $tmpPath = "imports/{$tmpKey}.csv";

        if (session('csv_import_key') !== $tmpKey || ! Storage::exists($tmpPath)) {
            return redirect()->route('school.students.import')
                ->with('error', 'Import session expired. Please re-upload your CSV.');
        }

        $rows    = $this->parseCSV(Storage::path($tmpPath));
        $school  = $this->currentSchool();
        $classes = SchoolClass::where('is_active', true)->get()->keyBy(fn ($c) => $this->classKey($c->grade, $c->section));
        $imported = 0;
        $skipped  = 0;

        foreach ($rows as $row) {
            if (! empty($this->validateRow($row, 0, $classes))) {
                $skipped++;
                continue;
            }

            DB::transaction(function () use ($row, $school, $classes, &$imported) {
                $classId = null;
                if (filled($row['grade'] ?? null) && filled($row['section'] ?? null)) {
                    $classId = $classes[$this->classKey($row['grade'], $row['section'])]?->id;
                }

                $student = Student::create([
                    'school_id'      => $school->id,
                    'uid'            => $this->generateUid($school),
                    'first_name'     => trim($row['first_name']),
                    'last_name'      => trim($row['last_name']),
                    'first_name_am'  => filled($row['first_name_am'] ?? null) ? trim($row['first_name_am']) : null,
                    'last_name_am'   => filled($row['last_name_am'] ?? null) ? trim($row['last_name_am']) : null,
                    'gender'         => strtolower(trim($row['gender'])),
                    'date_of_birth'  => $this->parseDate($row['date_of_birth'] ?? null),
                    'class_id'       => $classId,
                    'blood_type'     => filled($row['blood_type'] ?? null) ? trim($row['blood_type']) : null,
                    'status'         => filled($row['status'] ?? null) ? strtolower(trim($row['status'])) : 'active',
                    'medical_notes'  => filled($row['medical_notes'] ?? null) ? trim($row['medical_notes']) : null,
                    'enrollment_date' => today()->toDateString(),
                    'join_date'      => today()->toDateString(),
                ]);

                $this->qr->generate($student, $school);

                if (filled($row['guardian_name'] ?? null)) {
                    $this->guardians->attach($student, [
                        'full_name'    => trim($row['guardian_name']),
                        'phone'        => filled($row['guardian_phone'] ?? null) ? trim($row['guardian_phone']) : null,
                        'relationship' => filled($row['guardian_relationship'] ?? null)
                            ? strtolower(trim($row['guardian_relationship'])) : 'guardian',
                    ], true);
                }

                $imported++;
            });
        }

        Storage::delete($tmpPath);
        session()->forget(['csv_import_tmp', 'csv_import_key']);

        AuditLog::log('student.bulk_import', 'students', null, null, [
            'imported' => $imported,
            'skipped'  => $skipped,
        ]);

        return redirect()->route('school.students.index')
            ->with('success', "{$imported} student(s) imported successfully." . ($skipped ? " {$skipped} row(s) skipped." : ''));
    }

    /**
     * Serve a downloadable CSV template.
     */
    public function template()
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="students_import_template.csv"',
        ];

        $callback = function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, self::COLUMNS);
            fputcsv($out, [
                'Abebe', 'Kebede', 'አበበ', 'ከበደ', 'male',
                '2010-03-15', 'Grade 5', 'A', 'A+', 'active', '',
                'Kedir Kebede', '0911000001', 'father',
            ]);
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function requireFeature(): void
    {
        if (! $this->currentSchool()->hasFeature('csv_import')) {
            abort(403, 'CSV bulk import is not available on your current plan. Please upgrade to Standard or Premium.');
        }
    }

    private function parseCSV(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $handle = fopen($path, 'r');
        if (! $handle) {
            return [];
        }

        // Read and normalise headers.
        $rawHeaders = fgetcsv($handle);
        if (! $rawHeaders) {
            fclose($handle);
            return [];
        }

        $headers = array_map(fn ($h) => strtolower(trim(str_replace(' ', '_', $h))), $rawHeaders);

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            // Skip blank lines.
            if (count(array_filter($line, fn ($v) => $v !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = $line[$i] ?? '';
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function validateRow(array $row, int $lineNum, \Illuminate\Database\Eloquent\Collection $classes): array
    {
        $errors = [];

        foreach (self::REQUIRED as $col) {
            if (empty(trim($row[$col] ?? ''))) {
                $errors[] = "`{$col}` is required";
            }
        }

        $gender = strtolower(trim($row['gender'] ?? ''));
        if (! empty($gender) && ! in_array($gender, self::GENDERS)) {
            $errors[] = "gender must be 'male' or 'female'";
        }

        $status = strtolower(trim($row['status'] ?? ''));
        if (! empty($status) && ! in_array($status, self::STATUSES)) {
            $errors[] = "status must be one of: " . implode(', ', self::STATUSES);
        }

        $dob = trim($row['date_of_birth'] ?? '');
        if (! empty($dob) && $this->parseDate($dob) === null) {
            $errors[] = "date_of_birth must be a valid date (YYYY-MM-DD, DD/MM/YYYY, or DD-MM-YYYY)";
        }

        $bt = trim($row['blood_type'] ?? '');
        if (! empty($bt) && ! in_array($bt, self::BLOOD_TYPES)) {
            $errors[] = "blood_type must be one of: " . implode(', ', self::BLOOD_TYPES);
        }

        // grade + section together identify the class. If either is given, both
        // must be present and resolve to an active class.
        $grade   = trim($row['grade'] ?? '');
        $section = trim($row['section'] ?? '');
        if ($grade !== '' || $section !== '') {
            if ($grade === '' || $section === '') {
                $errors[] = "grade and section must both be provided to assign a class";
            } elseif (! $classes->has($this->classKey($grade, $section))) {
                $errors[] = "class '{$grade} - {$section}' not found — check active classes";
            }
        }

        // Ethiopian phone format (PRD §08): 09XXXXXXXX / 07XXXXXXXX or +2519.. / +2517..
        $phone = trim($row['guardian_phone'] ?? '');
        if ($phone !== '' && ! $this->isValidEthiopianPhone($phone)) {
            $errors[] = "guardian_phone must be a valid Ethiopian number (e.g. 0911000001 or +251911000001)";
        }

        $rel = strtolower(trim($row['guardian_relationship'] ?? ''));
        if (! empty($rel) && ! in_array($rel, self::RELATIONSHIPS)) {
            $errors[] = "guardian_relationship must be one of: " . implode(', ', self::RELATIONSHIPS);
        }

        return $errors;
    }

    /**
     * Build a stable lookup key for a class from grade + section,
     * e.g. ("Grade 9", "A") → "grade 9|a".
     */
    private function classKey(string $grade, string $section): string
    {
        return strtolower(trim($grade)) . '|' . strtolower(trim($section));
    }

    /**
     * Parse a date accepting YYYY-MM-DD, DD/MM/YYYY, or DD-MM-YYYY.
     * Returns a Y-m-d string, or null if blank/invalid.
     */
    private function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $format) {
            $dt = \DateTime::createFromFormat($format, $value);
            if ($dt !== false && $dt->format($format) === $value) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Validate an Ethiopian mobile number: 09/07 + 8 digits, or +251/251 + 9/7 + 8 digits.
     */
    private function isValidEthiopianPhone(string $phone): bool
    {
        $normalized = preg_replace('/[\s\-]/', '', $phone);

        return (bool) preg_match('/^(?:\+?251|0)(9|7)\d{8}$/', $normalized);
    }

    private function generateUid(School $school): string
    {
        $prefix = strtoupper($school->code) . '-';
        do {
            $uid = $prefix . str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        } while (Student::withoutGlobalScope('school')->where('uid', $uid)->exists());

        return $uid;
    }
}
