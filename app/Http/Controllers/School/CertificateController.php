<?php

namespace App\Http\Controllers\School;

use App\Models\Certificate;
use App\Models\LifecycleEvent;
use App\Models\Student;
use App\Services\CertificateService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Certificates: transfer letters, completion certificates, testimonials and
 * bonafide letters, generated from a student's own records.
 *
 * The wording is composed from enrolments and published marks, previewed live
 * while the registrar edits, then frozen on issue — the same contract as
 * transcripts, because a letter a family carries abroad must not change after
 * it has been signed.
 */
class CertificateController extends BaseSchoolController
{
    public function __construct(private CertificateService $certificates)
    {
    }

    /** Register of everything this school has issued. */
    public function index(Request $request)
    {
        $type = $request->query('type');
        $q    = trim((string) $request->query('q', ''));

        $query = Certificate::query()
            ->with(['student:id,first_name,last_name,uid', 'issuer:id,name'])
            ->when($type && array_key_exists($type, Certificate::TYPES), fn ($qq) => $qq->where('type', $type))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('reference', 'like', "%{$q}%")
                      ->orWhereHas('student', function ($s) use ($q) {
                          $s->where('first_name', 'like', "%{$q}%")
                            ->orWhere('last_name', 'like', "%{$q}%")
                            ->orWhere('uid', 'like', "%{$q}%");
                      });
                });
            })
            ->orderByDesc('issued_on')
            ->orderByDesc('id');

        $counts = Certificate::query()
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return Inertia::render('School/Certificates/Index', [
            'certificates' => $query->paginate(25)->withQueryString()->through(fn ($c) => [
                'id'          => $c->id,
                'reference'   => $c->reference,
                'type'        => $c->type,
                'type_label'  => $c->typeLabel(),
                'student'     => [
                    'id'   => $c->student?->id,
                    'name' => $c->student?->full_name,
                    'uid'  => $c->student?->uid,
                ],
                'addressed_to' => $c->addressed_to,
                'issued_on'    => $c->issued_on?->format('Y-m-d'),
                'issued_by'    => $c->issuer?->name,
                'is_revoked'   => $c->isRevoked(),
            ]),
            'counts'  => $counts,
            'total'   => array_sum($counts->toArray()),
            'types'   => collect(Certificate::TYPES)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
            'filters' => ['type' => $type, 'q' => $q],
            'is_admin' => auth()->user()?->isSchoolAdmin() ?? false,
        ]);
    }

    /** Compose a certificate for one student. */
    public function create(Request $request)
    {
        $student = null;

        if ($request->filled('student_id')) {
            $student = Student::findOrFail($request->integer('student_id'));
        }

        $type = (string) $request->query('type', 'transfer');
        if (! array_key_exists($type, Certificate::TYPES)) {
            $type = 'transfer';
        }

        return Inertia::render('School/Certificates/Create', [
            'student' => $student ? $this->studentPayload($student) : null,
            'type'    => $type,
            'types'   => collect(Certificate::TYPES)->map(fn ($label, $key) => [
                'value' => $key,
                'label' => $label,
                'hint'  => Certificate::TYPE_HINTS[$key] ?? null,
            ])->values(),
            'preview' => $student
                ? $this->certificates->build($student, $type, [
                    'body_note' => $type === 'character' ? $this->certificates->suggestedConductNote($student) : null,
                ])
                : null,
            'suggested_note' => $student ? $this->certificates->suggestedConductNote($student) : null,
            'next_reference' => $this->nextReference(),
            'schoolName'     => $this->currentSchool()->name,
            'is_admin'       => auth()->user()?->isSchoolAdmin() ?? false,
        ]);
    }

    /** Live preview as the registrar edits — JSON, called by the form. */
    public function preview(Request $request)
    {
        $data = $request->validate([
            'student_id'  => ['required', 'integer'],
            'type'        => ['required', Rule::in(array_keys(Certificate::TYPES))],
            'body_note'   => ['nullable', 'string', 'max:2000'],
            'addressed_to'=> ['nullable', 'string', 'max:150'],
        ]);

        $student = Student::findOrFail($data['student_id']);

        return response()->json([
            'preview'  => $this->certificates->build($student, $data['type'], [
                'body_note'    => $data['body_note'] ?? null,
                'addressed_to' => $data['addressed_to'] ?? null,
            ]),
            'student'  => $this->studentPayload($student),
            'suggested_note' => $this->certificates->suggestedConductNote($student),
        ]);
    }

    /** Student lookup for the picker. */
    public function searchStudents(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $students = Student::query()
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('first_name', 'like', "%{$q}%")
                      ->orWhere('last_name', 'like', "%{$q}%")
                      ->orWhere('uid', 'like', "%{$q}%");
                });
            })
            ->orderBy('first_name')
            ->limit(12)
            ->get(['id', 'uid', 'first_name', 'last_name', 'class_id', 'status'])
            ->map(fn ($s) => [
                'id'     => $s->id,
                'uid'    => $s->uid,
                'name'   => $s->full_name,
                'status' => $s->status,
            ]);

        return response()->json(['students' => $students]);
    }

    /** Freeze the letter and give it a serial. */
    public function store(Request $request)
    {
        $this->requireAdmin();

        $data = $request->validate([
            'student_id'   => ['required', 'integer', 'exists:students,id'],
            'type'         => ['required', Rule::in(array_keys(Certificate::TYPES))],
            'addressed_to' => ['nullable', 'string', 'max:150'],
            'body_note'    => ['nullable', 'string', 'max:2000'],
            'issued_on'    => ['nullable', 'date'],
        ]);

        $student = Student::findOrFail($data['student_id']);

        $snapshot = $this->certificates->build($student, $data['type'], [
            'body_note'    => $data['body_note'] ?? null,
            'addressed_to' => $data['addressed_to'] ?? null,
        ]);

        $certificate = DB::transaction(function () use ($student, $data, $snapshot) {
            return Certificate::create([
                'school_id'    => $student->school_id,
                'student_id'   => $student->id,
                'reference'    => $this->nextReference(),
                'type'         => $data['type'],
                'addressed_to' => $data['addressed_to'] ?? null,
                'issued_on'    => $data['issued_on'] ?? now()->toDateString(),
                'snapshot'     => $snapshot,
                'body_note'    => $data['body_note'] ?? null,
                'issued_by'    => auth()->id(),
            ]);
        });

        LifecycleEvent::create([
            'school_id'   => $student->school_id,
            'student_id'  => $student->id,
            'event'       => 'document_added',
            'occurred_on' => $certificate->issued_on,
            'notes'       => "{$certificate->typeLabel()} {$certificate->reference} issued.",
            'recorded_by' => auth()->id(),
        ]);

        return redirect()
            ->route('school.certificates.show', $certificate->id)
            ->with('success', "{$certificate->typeLabel()} {$certificate->reference} issued.");
    }

    /** View an issued (frozen) certificate. */
    public function show(Certificate $certificate)
    {
        $certificate->load(['student:id,first_name,last_name,uid', 'issuer:id,name']);

        return Inertia::render('School/Certificates/Show', [
            'certificate' => [
                'id'             => $certificate->id,
                'reference'      => $certificate->reference,
                'type'           => $certificate->type,
                'type_label'     => $certificate->typeLabel(),
                'addressed_to'   => $certificate->addressed_to,
                'issued_on'      => $certificate->issued_on?->format('Y-m-d'),
                'issued_by'      => $certificate->issuer?->name,
                'body_note'      => $certificate->body_note,
                'is_revoked'     => $certificate->isRevoked(),
                'revoked_reason' => $certificate->revoked_reason,
                'snapshot'       => $certificate->snapshot,
            ],
            'student'    => [
                'id'   => $certificate->student?->id,
                'name' => $certificate->student?->full_name,
                'uid'  => $certificate->student?->uid,
            ],
            'schoolName' => $this->currentSchool()->name,
            'is_admin'   => auth()->user()?->isSchoolAdmin() ?? false,
        ]);
    }

    /** Revoke a certificate issued in error. The row and its wording stay. */
    public function revoke(Request $request, Certificate $certificate)
    {
        $this->requireAdmin();

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $certificate->update([
            'revoked_at'     => now(),
            'revoked_reason' => $data['reason'],
        ]);

        return back()->with('success', "{$certificate->reference} revoked.");
    }

    // ── Helpers ────────────────────────────────────────────────

    private function studentPayload(Student $student): array
    {
        $current = $student->currentEnrollment()->with('cohort:id,grade,section')->first();

        return [
            'id'     => $student->id,
            'uid'    => $student->uid,
            'name'   => $student->full_name,
            'status' => $student->status,
            'class'  => $current?->cohort
                ? trim($current->cohort->grade . ' ' . $current->cohort->section)
                : null,
        ];
    }

    /** "CERT-AIA-0001" — per school, never reused. */
    private function nextReference(): string
    {
        $school = $this->currentSchool();
        $prefix = 'CERT-' . strtoupper($school->code) . '-';

        $last = Certificate::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('reference', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('reference');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
