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

        $staff = $query->orderBy('name')->paginate(20);

        return view('school.staff.index', compact('staff'));
    }

    public function create()
    {
        $this->requireAdmin();

        return view('school.staff.form', ['member' => new User()]);
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
        return view('school.staff.show', ['member' => $staff]);
    }

    public function edit(User $staff)
    {
        $this->requireAdmin();

        return view('school.staff.form', ['member' => $staff]);
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
