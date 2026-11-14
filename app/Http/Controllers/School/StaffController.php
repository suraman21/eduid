<?php

namespace App\Http\Controllers\School;

use App\Exceptions\StaffLimitReached;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\TeacherAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StaffController extends BaseSchoolController
{
    private const ROLES = ['school_admin', 'teacher', 'staff'];

    public function __construct(private TeacherAssignmentService $assignments)
    {
    }

    public function index(Request $request)
    {
        $query = User::whereIn('role', self::ROLES);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        if ($role = $request->get('role')) {
            $query->where('role', $role);
        }

        $staff = $query->orderBy('name')->paginate(20)->withQueryString();

        return Inertia::render('School/Staff/Index', [
            'staff' => $staff->through(fn ($m) => [
                'id'          => $m->id,
                'name'        => $m->name,
                'email'       => $m->email,
                'role'        => $m->role,
                'job_title'   => $m->job_title,
                'employee_id' => $m->employee_id,
                'is_active'   => (bool) $m->is_active,
                'is_homeroom' => (bool) $m->is_homeroom,
                'initials'    => strtoupper(substr($m->name, 0, 2)),
            ]),
            'filters'  => $request->only('search', 'role'),
            'is_admin' => auth()->user()?->isSchoolAdmin() ?? false,
        ]);
    }

    public function create()
    {
        $this->requireAdmin();
        return Inertia::render('School/Staff/Form', [
            'member'   => null,
            'classes'  => $this->classOptions(),
            'subjects' => $this->subjectOptions(),
            'current_assignments' => ['assignments' => [], 'homeroom_class_ids' => []],
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $validated  = $this->validateStaff($request);
        $assignment = $this->validateAssignments($request);

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = true;
        $validated['can_login'] = $request->boolean('can_login', true);
        $validated['is_coordinator'] = $request->boolean('is_coordinator');
        $validated['can_record_staff_attendance'] = $request->boolean('can_record_staff_attendance');

        try {
            $member = $this->createStaffAtomically($validated);
        } catch (StaffLimitReached $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        if ($member->isTeacher()) {
            $this->assignments->sync($member, $assignment['assignments'], $assignment['homeroom_class_ids']);
        }

        AuditLog::log('staff.created', 'users', $member->id, null, ['name' => $member->name, 'role' => $member->role]);

        return redirect()->route('school.staff.index')->with('success', 'Staff member added.');
    }

    public function show(User $staff)
    {
        $this->assertManageable($staff);

        return Inertia::render('School/Staff/Show', [
            'member' => [
                'id'                            => $staff->id,
                'name'                          => $staff->name,
                'email'                         => $staff->email,
                'role'                          => $staff->role,
                'job_title'                     => $staff->job_title,
                'phone'                         => $staff->phone,
                'employee_id'                   => $staff->employee_id,
                'is_active'                     => (bool) $staff->is_active,
                'can_login'                     => (bool) $staff->can_login,
                'is_homeroom'                   => (bool) $staff->is_homeroom,
                'is_coordinator'                => (bool) $staff->is_coordinator,
                'can_record_staff_attendance'   => (bool) $staff->can_record_staff_attendance,
                'last_login_at'                 => $staff->last_login_at?->diffForHumans(),
                'initials'                      => strtoupper(substr($staff->name, 0, 2)),
            ],
            'teaching' => $staff->isTeacher() ? $this->teachingSummary($staff) : null,
            'documents' => $staff->staffDocuments()
                ->with('uploader:id,name')
                ->get()
                ->map(fn ($d) => [
                    'id'           => $d->id,
                    'type'         => $d->type,
                    'type_label'   => $d->typeLabel(),
                    'title'        => $d->title,
                    'description'  => $d->description,
                    'file_name'    => $d->original_name,
                    'file_size'    => $d->humanSize(),
                    'is_pdf'       => $d->isPdf(),
                    'is_image'     => $d->isImage(),
                    'issued_on'    => $d->issued_on?->format('Y-m-d'),
                    'expires_on'   => $d->expires_on?->format('Y-m-d'),
                    'expiry_state' => $d->expiryState(),
                    'days_left'    => $d->daysUntilExpiry(),
                    'is_expired'   => $d->isExpired(),
                    'is_verified'  => (bool) $d->is_verified,
                    'uploaded_by'  => $d->uploader?->name,
                ]),
            'document_types' => collect(\App\Models\StaffDocument::TYPES)
                ->map(fn ($l, $k) => ['value' => $k, 'label' => $l])->values(),
            'warn_days' => \App\Models\StaffDocument::WARN_DAYS,
            'is_admin' => auth()->user()?->isSchoolAdmin() ?? false,
        ]);
    }

    public function edit(User $staff)
    {
        $this->requireAdmin();
        $this->assertManageable($staff);

        return Inertia::render('School/Staff/Form', [
            'member' => [
                'id'                            => $staff->id,
                'name'                          => $staff->name,
                'email'                         => $staff->email,
                'role'                          => $staff->role,
                'phone'                         => $staff->phone,
                'employee_id'                   => $staff->employee_id,
                'job_title'                     => $staff->job_title,
                'is_active'                     => (bool) $staff->is_active,
                'can_login'                     => (bool) $staff->can_login,
                'is_coordinator'                => (bool) $staff->is_coordinator,
                'can_record_staff_attendance'   => (bool) $staff->can_record_staff_attendance,
            ],
            'classes'  => $this->classOptions($staff->id),
            'subjects' => $this->subjectOptions(),
            'current_assignments' => $staff->isTeacher()
                ? $this->assignments->current($staff)
                : ['assignments' => [], 'homeroom_class_ids' => []],
        ]);
    }

    public function update(Request $request, User $staff)
    {
        $this->requireAdmin();
        $this->assertManageable($staff);

        $validated  = $this->validateStaff($request, $staff->id);
        $assignment = $this->validateAssignments($request);

        // Self-lockout guard: an admin editing their OWN account cannot
        // demote themselves or switch off their own access — if they are the
        // school's only admin, that would lock the whole school out with no
        // self-service way back in.
        if ($staff->id === auth()->id()) {
            if ($validated['role'] !== 'school_admin'
                || ! $request->boolean('is_active')
                || ! $request->boolean('can_login')
            ) {
                return back()->with('error',
                    'You cannot demote or deactivate your own account. Ask another administrator to change it.')
                    ->withInput();
            }
        }

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $validated['is_active'] = $request->boolean('is_active');
        $validated['can_login'] = $request->boolean('can_login');
        $validated['is_coordinator'] = $request->boolean('is_coordinator');
        $validated['can_record_staff_attendance'] = $request->boolean('can_record_staff_attendance');

        $old = $staff->only(array_keys(array_diff_key($validated, ['password' => ''])));
        $staff->update($validated);

        // Teachers get the submitted assignment set; a role change away from
        // teacher releases every class-subject slot and homeroom they held.
        if ($staff->isTeacher()) {
            $this->assignments->sync($staff, $assignment['assignments'], $assignment['homeroom_class_ids']);
        } else {
            $this->assignments->clear($staff);
        }

        AuditLog::log('staff.updated', 'users', $staff->id, $old, array_diff_key($validated, ['password' => '']));

        return redirect()->route('school.staff.show', $staff)->with('success', 'Staff member updated.');
    }

    /**
     * The staff pages manage staff ROLES only. Route-model binding on User
     * would otherwise also resolve parent accounts (same table, same
     * school), letting an admin "edit" a parent here and flip their role —
     * which would orphan the guardian↔user link. Parents are managed from
     * the student page.
     */
    private function assertManageable(User $staff): void
    {
        if (! in_array($staff->role, self::ROLES, true)) {
            abort(404);
        }
    }

    /**
     * Class options for the assignment picker, with the current homeroom
     * holder so the admin sees who they would be taking the class from.
     */
    private function classOptions(?int $exceptTeacherId = null): array
    {
        return SchoolClass::where('is_active', true)
            ->with('homeroomTeacher:id,name')
            ->orderBy('level_order')->orderBy('grade')->orderBy('section')
            ->get()
            ->map(fn ($c) => [
                'id'    => $c->id,
                'name'  => $c->display_name,
                'homeroom_teacher' => $c->homeroom_teacher_id && $c->homeroom_teacher_id !== $exceptTeacherId
                    ? $c->homeroomTeacher?->name
                    : null,
            ])->all();
    }

    private function subjectOptions(): array
    {
        return Subject::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'code' => $s->code])
            ->all();
    }

    /**
     * Human summary for the profile page: "Grade 1 - A → English, Homeroom".
     */
    private function teachingSummary(User $teacher): array
    {
        $current = $this->assignments->current($teacher);

        $classes = SchoolClass::whereIn(
            'id',
            collect($current['assignments'])->pluck('class_id')
                ->merge($current['homeroom_class_ids'])->unique()
        )->get()->keyBy('id');

        $subjects = Subject::whereIn(
            'id',
            collect($current['assignments'])->pluck('subject_id')->filter()->unique()
        )->get()->keyBy('id');

        $byClass = [];
        foreach ($current['assignments'] as $a) {
            $byClass[$a['class_id']]['subjects'][] = $a['subject_id']
                ? ($subjects[$a['subject_id']]->name ?? '?')
                : null;
        }
        foreach ($current['homeroom_class_ids'] as $cid) {
            $byClass[$cid]['homeroom'] = true;
        }

        return collect($byClass)->map(fn ($info, $cid) => [
            'class_id'   => (int) $cid,
            'class_name' => $classes[$cid]->display_name ?? '?',
            'subjects'   => array_values(array_filter($info['subjects'] ?? [])),
            'homeroom'   => (bool) ($info['homeroom'] ?? false),
        ])->values()->all();
    }

    /**
     * Create a staff account atomically, enforcing the teacher cap under a row
     * lock. Only the 'teacher' role counts against max_teachers; admins and
     * support staff are unlimited. Locking the school row serializes
     * simultaneous hires for the same school so the cap can't be oversold,
     * while different schools stay fully parallel.
     *
     * @throws StaffLimitReached
     */
    private function createStaffAtomically(array $attributes): User
    {
        $schoolId = $this->schoolId();

        return DB::transaction(function () use ($attributes, $schoolId) {
            if (($attributes['role'] ?? null) === 'teacher') {
                $school = School::whereKey($schoolId)->lockForUpdate()->firstOrFail();

                $teacherCount = User::withoutGlobalScope('school')
                    ->where('school_id', $schoolId)
                    ->where('role', 'teacher')
                    ->where('is_active', true)
                    ->count();

                if ($teacherCount >= $school->max_teachers) {
                    throw new StaffLimitReached(
                        "Teacher limit ({$school->max_teachers}) reached. Upgrade your plan."
                    );
                }
            }

            return User::create($attributes);
        });
    }

    private function validateStaff(Request $request, ?int $ignoreId = null): array
    {
        $passwordRule = $ignoreId ? 'nullable|string|min:8' : 'required|string|min:8';

        return $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => [
                'required', 'email', 'max:255',
                Rule::unique('users')->where('school_id', $this->schoolId())->ignore($ignoreId),
            ],
            'password'    => $passwordRule,
            'role'        => ['required', Rule::in(self::ROLES)],
            'phone'       => 'nullable|string|max:20',
            'employee_id' => [
                'nullable', 'string', 'max:30',
                Rule::unique('users')->where('school_id', $this->schoolId())->ignore($ignoreId),
            ],
            'job_title'   => 'nullable|string|max:100',
        ]);
    }

    /**
     * Validate the teaching-assignment payload. Tenant ownership of every
     * class/subject id is re-checked inside TeacherAssignmentService.
     *
     * @return array{assignments: array, homeroom_class_ids: array}
     */
    private function validateAssignments(Request $request): array
    {
        $validated = $request->validate([
            'assignments'                => 'nullable|array|max:60',
            'assignments.*.class_id'     => 'required|integer',
            'assignments.*.subject_id'   => 'nullable|integer',
            'homeroom_class_ids'         => 'nullable|array|max:10',
            'homeroom_class_ids.*'       => 'integer',
        ]);

        return [
            'assignments'        => $validated['assignments'] ?? [],
            'homeroom_class_ids' => array_map('intval', $validated['homeroom_class_ids'] ?? []),
        ];
    }
}
