<?php

namespace App\Http\Controllers\School;

use App\Exceptions\StudentLimitReached;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Services\QrTokenService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentController extends BaseSchoolController
{
    /** Allowed student lifecycle states (matches the DB enum). */
    private const STATUSES = ['active', 'promoted', 'repeating', 'graduated', 'transferred', 'withdrawn'];

    public function index(Request $request)
    {
        $query = Student::with('schoolClass');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('uid', 'like', "%{$search}%");
            });
        }

        if ($classId = $request->get('class_id')) {
            $query->where('class_id', $classId);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $students = $query->orderBy('first_name')->paginate(20);
        $classes  = $this->activeClasses();

        return view('school.students.index', compact('students', 'classes'));
    }

    public function create()
    {
        $this->requireAdmin();

        return view('school.students.form', [
            'student' => new Student(),
            'classes' => $this->activeClasses(),
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $validated = $this->validateStudent($request);
        $validated['status']          = $validated['status'] ?? 'active';
        $validated['join_date']       = $validated['join_date'] ?? today()->toDateString();
        $validated['enrollment_date'] = $validated['enrollment_date'] ?? today()->toDateString();

        // Store the photo BEFORE the transaction (filesystem I/O should not
        // hold a DB row lock open). If the transaction later fails we clean up.
        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store("schools/{$this->schoolId()}/students", 'public');
        }

        try {
            $student = $this->createStudentAtomically($validated);
        } catch (StudentLimitReached $e) {
            if (!empty($validated['photo_path'])) {
                Storage::disk('public')->delete($validated['photo_path']);
            }

            return back()->with('error', $e->getMessage())->withInput();
        }

        // Issue the QR token for the current academic year. Done outside the
        // creation transaction (it's a single self-contained update) so the
        // school-row lock is released as early as possible. A student is never
        // left without a token — the scanner/card pipeline depends on it.
        app(QrTokenService::class)->generate($student, $this->currentSchool());

        AuditLog::log('student.created', 'students', $student->id, null, ['uid' => $student->uid, 'name' => $student->full_name]);

        return redirect()->route('school.students.index')->with('success', 'Student added successfully.');
    }

    public function show(Student $student)
    {
        $student->load('schoolClass', 'guardians');

        return view('school.students.show', compact('student'));
    }

    public function edit(Student $student)
    {
        $this->requireAdmin();

        return view('school.students.form', [
            'student' => $student,
            'classes' => $this->activeClasses(),
        ]);
    }

    public function update(Request $request, Student $student)
    {
        $this->requireAdmin();

        $validated = $this->validateStudent($request);

        if ($request->hasFile('photo')) {
            if ($student->photo_path) {
                Storage::disk('public')->delete($student->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store("schools/{$this->schoolId()}/students", 'public');
        }

        $old = $student->only(array_keys($validated));
        $student->update($validated);

        AuditLog::log('student.updated', 'students', $student->id, $old, $validated);

        return redirect()->route('school.students.show', $student)->with('success', 'Student updated.');
    }

    /**
     * Active classes for dropdowns (auto-scoped to the current school).
     */
    private function activeClasses()
    {
        return SchoolClass::where('is_active', true)->orderBy('grade')->get();
    }

    private function validateStudent(Request $request): array
    {
        return $request->validate([
            'first_name'      => 'required|string|max:100',
            'last_name'       => 'required|string|max:100',
            'first_name_am'   => 'nullable|string|max:100',
            'last_name_am'    => 'nullable|string|max:100',
            'gender'          => 'required|in:male,female',
            'date_of_birth'   => 'nullable|date|before:today',
            'class_id'        => 'nullable|exists:classes,id',
            'blood_type'      => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'medical_notes'   => 'nullable|string|max:500',
            'enrollment_date' => 'nullable|date',
            'status'          => ['nullable', 'in:' . implode(',', self::STATUSES)],
            'join_date'       => 'nullable|date',
            'end_date'        => 'nullable|date|after_or_equal:join_date',
            'photo'           => 'nullable|image|max:2048',
        ]);
    }

    /**
     * Create a student atomically, enforcing the plan's student limit under a
     * row lock and tolerating UID collisions.
     *
     * Concurrency design:
     *  - The whole operation runs in a transaction.
     *  - We lock the school row (SELECT ... FOR UPDATE) so that simultaneous
     *    enrollments for the SAME school serialize on the limit check. This
     *    prevents the "oversell" race where many concurrent requests all read
     *    count < max and then all insert, blowing past the paid limit.
     *    Enrollments for DIFFERENT schools lock different rows and stay fully
     *    parallel — no global contention.
     *  - The globally-unique `uid` is generated and inserted; if two requests
     *    ever pick the same random UID, the unique index rejects the loser and
     *    we retry with a fresh UID instead of surfacing a 500.
     *
     * @throws StudentLimitReached
     */
    private function createStudentAtomically(array $attributes): Student
    {
        $schoolId = $this->schoolId();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(function () use ($attributes, $schoolId) {
                    // Lock the school row for the duration of the limit check.
                    $school = School::whereKey($schoolId)->lockForUpdate()->firstOrFail();

                    // Count by `status` (authoritative column). The legacy
                    // `is_active` boolean is kept for compatibility but is no
                    // longer the seat-allocation signal.
                    $activeCount = Student::withoutGlobalScope('school')
                        ->where('school_id', $schoolId)
                        ->where('status', 'active')
                        ->count();

                    if ($activeCount >= $school->max_students) {
                        throw new StudentLimitReached(
                            "Student limit ({$school->max_students}) reached. Upgrade your plan."
                        );
                    }

                    $attributes['uid'] = $this->generateUid($school);

                    return Student::create($attributes);
                });
            } catch (QueryException $e) {
                // Retry only on a duplicate-UID collision; rethrow anything else.
                if ($this->isDuplicateUid($e) && $attempt < 4) {
                    continue;
                }
                throw $e;
            }
        }

        // Exhausted retries — astronomically unlikely, but fail loudly.
        throw ValidationException::withMessages([
            'uid' => 'Could not allocate a unique student ID. Please try again.',
        ]);
    }

    /**
     * Generate a candidate globally-unique student UID.
     *
     * The `uid` column is unique across ALL schools, so the pre-check bypasses
     * the per-school global scope. The check is a best-effort fast path only —
     * the database unique index is the real source of truth and the caller
     * retries on collision.
     */
    private function generateUid(School $school): string
    {
        // Format: {SCHOOL_CODE}-{5-DIGIT-RANDOM}  e.g. AIA-65289
        $prefix = strtoupper($school->code) . '-';

        do {
            $uid = $prefix . str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        } while (Student::withoutGlobalScope('school')->where('uid', $uid)->exists());

        return $uid;
    }

    /**
     * Detect a unique-constraint violation on the students.uid index.
     */
    private function isDuplicateUid(QueryException $e): bool
    {
        // SQLSTATE 23000 = integrity constraint violation (MySQL & SQLite).
        return $e->getCode() === '23000'
            && str_contains($e->getMessage(), 'uid');
    }
}
