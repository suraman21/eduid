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
use Inertia\Inertia;

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

        // "Missing photo" worklist — pairs with photo sessions so the admin
        // can chase exactly who still needs a picture.
        if ($request->boolean('no_photo')) {
            $query->whereNull('photo_path');
        }

        // Surface unassigned students (no class) first so admins notice and
        // place them. SQLite + MySQL compatible.
        $students = $query
            ->orderByRaw('CASE WHEN class_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('first_name')
            ->paginate(20);

        $unassignedCount = (clone $query)->whereNull('class_id')->count();
        $classes         = $this->activeClasses();

        return Inertia::render('School/Students/Index', [
            'students'         => $students->through(fn ($s) => [
                'id'         => $s->id,
                'full_name'  => $s->full_name,
                'uid'        => $s->uid,
                'status'     => $s->status,
                'photo_url'  => $s->photo_url,
                'initials'   => collect(explode(' ', $s->full_name))->map(fn ($w) => strtoupper($w[0] ?? ''))->take(2)->implode(''),
                'class_name' => $s->schoolClass?->name,
                'gender'     => $s->gender,
            ]),
            'classes'          => $classes->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]),
            'unassigned_count' => $unassignedCount,
            'filters'          => $request->only('search', 'class_id', 'status', 'no_photo'),
        ]);
    }

    public function create()
    {
        $this->requireAdmin();

        return Inertia::render('School/Students/Form', [
            'student' => null,
            'classes' => $this->activeClasses()->map(fn($c) => ['id' => $c->id, 'name' => $c->name]),
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
            $validated['photo_path'] = $request->file('photo')->store("schools/{$this->schoolId()}/students", 'local');
        }

        try {
            $student = $this->createStudentAtomically($validated);
        } catch (StudentLimitReached $e) {
            if (!empty($validated['photo_path'])) {
                Storage::disk('local')->delete($validated['photo_path']);
            }

            return back()->with('error', $e->getMessage())->withInput();
        }

        // Issue the QR token for the current academic year. Done outside the
        // creation transaction (it's a single self-contained update) so the
        // school-row lock is released as early as possible. A student is never
        // left without a token — the scanner/card pipeline depends on it.
        app(QrTokenService::class)->generate($student, $this->currentSchool());

        // Attach any guardians entered inline on the registration form. Done
        // after creation so the student id exists for the pivot links.
        $this->attachGuardiansFromRequest($request, $student);

        AuditLog::log('student.created', 'students', $student->id, null, ['uid' => $student->uid, 'name' => $student->full_name]);

        return redirect()->route('school.students.index')->with('success', 'Student added successfully.');
    }

    public function show(Student $student)
    {
        $student->load('schoolClass', 'guardians.user');

        $enrollmentService = app(\App\Services\EnrollmentService::class);
        $current = $enrollmentService->currentFor($student);

        $profile = \App\Models\StudentProfile::firstOrCreate(
            ['student_id' => $student->id],
            ['school_id' => $student->school_id],
        );

        $classes = SchoolClass::query()
            ->where('is_active', true)
            ->orderBy('level_order')
            ->orderBy('grade')
            ->orderBy('section')
            ->get()
            ->map(fn ($c) => [
                'id'             => $c->id,
                'label'          => $c->lifecycle_label,
                'academic_year'  => $c->academic_year,
                'seats_remaining'=> $c->seatsRemaining(),
            ]);

        return Inertia::render('School/Students/Show', [
            'student' => [
                'id'              => $student->id,
                'full_name'       => $student->full_name,
                'full_name_am'    => $student->full_name_am,
                'uid'             => $student->uid,
                'gender'          => $student->gender,
                'date_of_birth'   => $student->date_of_birth?->format('Y-m-d'),
                'blood_type'      => $student->blood_type,
                'status'          => $student->status ?? 'active',
                'photo_url'       => $student->photo_url,
                'initials'        => collect(explode(' ', $student->full_name))->map(fn($w) => strtoupper($w[0] ?? ''))->take(2)->implode(''),
                'class_name'      => $student->schoolClass?->name,
                'enrollment_date' => $student->enrollment_date?->format('Y-m-d'),
                'join_date'       => $student->join_date?->format('Y-m-d'),
                'end_date'        => $student->end_date?->format('Y-m-d'),
                'medical_notes'   => $student->medical_notes,
                'qr_token'        => $student->qr_token,
                'qr_token_year'   => $student->qr_token_year,
                'qr_valid'        => $student->hasValidQrToken(),
                'created_at'      => $student->created_at->format('M d, Y'),
                'guardians'       => $student->guardians->map(fn($g) => [
                    'id'           => $g->id,
                    'full_name'    => $g->full_name,
                    'phone'        => $g->phone,
                    'phone_alt'    => $g->phone_alt,
                    'email'        => $g->email,
                    'relationship' => $g->relationship,
                    'is_primary'   => (bool) $g->pivot->is_primary,
                    // Parent-app login state (null = no account yet).
                    'app_access'   => $g->user
                        ? ($g->user->is_active && $g->user->can_login ? 'active' : 'revoked')
                        : null,
                    'account_email' => $g->user?->email,
                    // Telegram absence-alert linking ('alerts' module).
                    'telegram_linked' => (bool) $g->telegram_chat_id,
                    'telegram_link'   => app(\App\Services\TelegramService::class)->deepLink($g),
                ]),
            ],

            /* ── Lifecycle: the 360° record ─────────────────────── */
            'lifecycle' => [
                'current' => $current ? $this->enrollmentPayload($current) : null,
                'history' => $student->enrollments()
                    ->with('cohort')
                    ->get()
                    ->map(fn ($e) => $this->enrollmentPayload($e)),
                'events'  => $student->lifecycleEvents()
                    ->with('recordedBy:id,name')
                    ->get()
                    ->map(fn ($ev) => [
                        'id'          => $ev->id,
                        'event'       => $ev->event,
                        'label'       => $ev->label(),
                        'occurred_on' => $ev->occurred_on?->format('Y-m-d'),
                        'from_label'  => $ev->from_label,
                        'to_label'    => $ev->to_label,
                        'notes'       => $ev->notes,
                        'by'          => $ev->recordedBy?->name,
                        'is_exit'     => in_array($ev->event, \App\Models\LifecycleEvent::EXIT_EVENTS, true),
                    ]),
                'profile' => $profile->only([
                    'national_id', 'birth_place', 'nationality', 'mother_tongue', 'religion',
                    'region', 'zone', 'woreda', 'kebele', 'house_number', 'address_line',
                    'phone', 'email',
                    'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
                    'previous_school', 'previous_school_year', 'previous_grade', 'admission_source',
                    'allergies', 'chronic_conditions', 'insurance_provider', 'insurance_number',
                    'special_needs', 'exit_reason_category', 'exit_note', 'exit_destination',
                ]),
                'documents' => $student->documents()->get()->map(fn ($d) => [
                    'id'          => $d->id,
                    'type'        => $d->type,
                    'type_label'  => $d->typeLabel(),
                    'title'       => $d->title,
                    'description' => $d->description,
                    'original_name' => $d->original_name,
                    'mime_type'   => $d->mime_type,
                    'size'        => $d->humanSize(),
                    'is_image'    => $d->isImage(),
                    'is_pdf'      => $d->isPdf(),
                    'issued_on'   => $d->issued_on?->format('Y-m-d'),
                    'is_verified' => (bool) $d->is_verified,
                    'created_at'  => $d->created_at?->format('d M Y'),
                    'view_url'    => route('school.students.documents.show', [$student->id, $d->id]),
                    'download_url'=> route('school.students.documents.download', [$student->id, $d->id]),
                ]),
                'classes'     => $classes,
                'document_types' => collect(\App\Models\StudentDocument::TYPES)
                    ->map(fn ($label, $key) => ['value' => $key, 'label' => $label])
                    ->values(),
            ],

            'is_admin'   => auth()->user()?->isSchoolAdmin() ?? false,
            'parent_app' => $this->currentSchool()->hasFeature('parent_app'),
            'alerts_on'  => $this->currentSchool()->hasFeature('alerts'),
        ]);
    }

    /** Shape one enrolment row for the lifecycle UI. */
    private function enrollmentPayload(\App\Models\StudentEnrollment $e): array
    {
        return [
            'id'            => $e->id,
            'label'         => $e->label(),
            'class_name'    => $e->cohort
                ? trim($e->cohort->grade . ' ' . $e->cohort->section)
                : null,
            'academic_year' => $e->academic_year,
            'term_label'    => $e->term_label,
            'level_number'  => $e->level_number,
            'status'        => $e->status,
            'exit_reason'   => $e->exit_reason,
            'exit_note'     => $e->exit_note,
            'destination_school' => $e->destination_school,
            'enrolled_on'   => $e->enrolled_on?->format('Y-m-d'),
            'ended_on'      => $e->ended_on?->format('Y-m-d'),
            'is_current'    => (bool) $e->is_current,
            'duration'      => $e->durationLabel(),
        ];
    }

    /**
     * Stream a student's photo through an authenticated, tenant-scoped route
     * (PRD §10). Route-model binding + the BelongsToSchool global scope mean a
     * cross-tenant student id resolves to 404, never another school's photo.
     *
     * New uploads live on the private disk; we fall back to the legacy public
     * disk so photos uploaded before this change keep resolving.
     */
    public function photo(Student $student)
    {
        // Teachers may only load photos of students in their own classes
        // (admins/staff pass). Route ring 1 admits every school role, so the
        // scoping the route comment promises is enforced HERE.
        $this->requireTeacherCanAccessClass($student->class_id);

        if (! $student->photo_path) {
            abort(404);
        }

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($student->photo_path)) {
                return Storage::disk($disk)->response($student->photo_path, null, [
                    'Cache-Control' => 'private, max-age=3600',
                ]);
            }
        }

        abort(404);
    }

    public function edit(Student $student)
    {
        $this->requireAdmin();

        return Inertia::render('School/Students/Form', [
            'student' => [
                'id'              => $student->id,
                'first_name'      => $student->first_name,
                'last_name'       => $student->last_name,
                'first_name_am'   => $student->first_name_am,
                'last_name_am'    => $student->last_name_am,
                'gender'          => $student->gender,
                'date_of_birth'   => $student->date_of_birth?->format('Y-m-d'),
                'blood_type'      => $student->blood_type,
                'status'          => $student->status ?? 'active',
                'class_id'        => $student->class_id,
                'medical_notes'   => $student->medical_notes,
                'enrollment_date' => $student->enrollment_date?->format('Y-m-d'),
                'join_date'       => $student->join_date?->format('Y-m-d'),
                'end_date'        => $student->end_date?->format('Y-m-d'),
                'photo_url'       => $student->photo_url,
            ],
            'classes' => $this->activeClasses()->map(fn($c) => ['id' => $c->id, 'name' => $c->name]),
        ]);
    }

    public function update(Request $request, Student $student)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $validated = $this->validateStudent($request);

        if ($request->hasFile('photo')) {
            if ($student->photo_path) {
                // Old photo may live on either disk (legacy public or new private).
                Storage::disk('local')->delete($student->photo_path);
                Storage::disk('public')->delete($student->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store("schools/{$this->schoolId()}/students", 'local');
        }

        $old = $student->only(array_keys($validated));
        $student->update($validated);

        // Attach any newly-entered guardians (existing links are untouched;
        // edits/removals of existing guardians happen on the profile page).
        $this->attachGuardiansFromRequest($request, $student);

        AuditLog::log('student.updated', 'students', $student->id, $old, $validated);

        return redirect()->route('school.students.show', $student)->with('success', 'Student updated.');
    }

    /**
     * Validate and attach the inline guardian rows from a student form.
     *
     * The form submits `guardians[]` rows plus a `primary_guardian` index
     * naming which row is the primary contact. Blank rows are ignored. The
     * find-or-create + primary logic lives in GuardianService.
     */
    private function attachGuardiansFromRequest(Request $request, Student $student): void
    {
        if (! is_array($request->input('guardians'))) {
            return;
        }

        $validated = $request->validate([
            'guardians'                 => 'array',
            'guardians.*.id'            => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('guardians', 'id')->where('school_id', $this->schoolId())],
            'guardians.*.full_name'     => 'nullable|string|max:200',
            'guardians.*.phone'         => 'nullable|string|max:20',
            'guardians.*.phone_alt'     => 'nullable|string|max:20',
            'guardians.*.email'         => 'nullable|email|max:100',
            'guardians.*.relationship'  => ['nullable', \Illuminate\Validation\Rule::in(\App\Services\GuardianService::RELATIONSHIPS)],
            'primary_guardian'          => 'nullable|integer',
        ]);

        $rows = array_map(function ($row) {
            $row['relationship'] = $row['relationship'] ?? 'guardian';
            return $row;
        }, $validated['guardians']);

        $primaryIndex = isset($validated['primary_guardian']) ? (int) $validated['primary_guardian'] : null;

        app(\App\Services\GuardianService::class)->attachMany($student, $rows, $primaryIndex);
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
            // Tenant-scoped: a foreign school's class id must never validate.
            'class_id'        => ['nullable', \Illuminate\Validation\Rule::exists('classes', 'id')->where('school_id', $this->schoolId())],
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
