<?php

namespace App\Http\Controllers\School;

use App\Exceptions\StaffLimitReached;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StaffController extends BaseSchoolController
{
    private const ROLES = ['school_admin', 'teacher', 'staff'];

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
            'member' => null,
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $validated = $this->validateStaff($request);
        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = true;
        $validated['can_login'] = $request->boolean('can_login', true);
        $validated['is_homeroom'] = $request->boolean('is_homeroom');
        $validated['is_coordinator'] = $request->boolean('is_coordinator');
        $validated['can_record_staff_attendance'] = $request->boolean('can_record_staff_attendance');

        try {
            $member = $this->createStaffAtomically($validated);
        } catch (StaffLimitReached $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        AuditLog::log('staff.created', 'users', $member->id, null, ['name' => $member->name, 'role' => $member->role]);

        return redirect()->route('school.staff.index')->with('success', 'Staff member added.');
    }

    public function show(User $staff)
    {
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
            'is_admin' => auth()->user()?->isSchoolAdmin() ?? false,
        ]);
    }

    public function edit(User $staff)
    {
        $this->requireAdmin();
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
                'is_homeroom'                   => (bool) $staff->is_homeroom,
                'is_coordinator'                => (bool) $staff->is_coordinator,
                'can_record_staff_attendance'   => (bool) $staff->can_record_staff_attendance,
            ],
        ]);
    }

    public function update(Request $request, User $staff)
    {
        $this->requireAdmin();

        $validated = $this->validateStaff($request, $staff->id);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $validated['is_active'] = $request->boolean('is_active');
        $validated['can_login'] = $request->boolean('can_login');
        $validated['is_homeroom'] = $request->boolean('is_homeroom');
        $validated['is_coordinator'] = $request->boolean('is_coordinator');
        $validated['can_record_staff_attendance'] = $request->boolean('can_record_staff_attendance');

        $old = $staff->only(array_keys(array_diff_key($validated, ['password' => ''])));
        $staff->update($validated);

        AuditLog::log('staff.updated', 'users', $staff->id, $old, array_diff_key($validated, ['password' => '']));

        return redirect()->route('school.staff.show', $staff)->with('success', 'Staff member updated.');
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
}
