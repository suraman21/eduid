<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Self-service profile management for BOTH portals. The auth middleware sets
 * the active guard per route group (web for schools, admin for super admins),
 * so auth()->user() is always the right account here — one controller serves
 * /school/profile and /admin/profile.
 *
 * Security posture:
 *  - changing the EMAIL (a credential — school login is code+email) requires
 *    the current password;
 *  - changing the PASSWORD requires the current password, revokes all mobile
 *    API tokens, and rotates the session ID;
 *  - every change is audit-logged with old→new values.
 */
class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('school');
        $isAdminPortal = $request->routeIs('admin.*');

        return Inertia::render($isAdminPortal ? 'Admin/Profile' : 'School/Profile', [
            'profile' => [
                'name'        => $user->name,
                'email'       => $user->email,
                'phone'       => $user->phone,
                'role'        => $user->role,
                'job_title'   => $user->job_title,
                'employee_id' => $user->employee_id,
                'school'      => $user->school ? ['name' => $user->school->name, 'code' => $user->school->code] : null,
                'last_login'  => $user->last_login_at?->format('M d, Y H:i'),
                'member_since' => $user->created_at?->format('M d, Y'),
            ],
            'updateUrl'   => $isAdminPortal ? route('admin.profile.update') : route('school.profile.update'),
            'passwordUrl' => $isAdminPortal ? route('admin.profile.password') : route('school.profile.password'),
        ]);
    }

    /** Update name / email / phone. Email changes require the current password. */
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:100',
                Rule::unique('users', 'email')
                    ->where(fn ($q) => $q->where('school_id', $user->school_id))
                    ->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'current_password' => ['nullable', 'string'],
        ]);

        $emailChanged = strcasecmp($data['email'], $user->email) !== 0;

        // Email is a login credential — re-authenticate before changing it.
        if ($emailChanged) {
            if (empty($data['current_password']) || ! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Enter your current password to change your email address.'],
                ]);
            }
        }

        $old = $user->only(['name', 'email', 'phone']);
        $user->update([
            'name'  => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
        ]);

        AuditLog::safeRecord(
            $user->school_id, $user->id, 'user.profile_updated', 'users', $user->id,
            $old, $user->only(['name', 'email', 'phone'])
        );

        return back()->with('success', $emailChanged
            ? 'Profile updated. Use your new email address next time you sign in.'
            : 'Profile updated.');
    }

    /** Change password: verify current, revoke mobile tokens, rotate session. */
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Your current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        // Any mobile (Sanctum) tokens were minted under the old credential —
        // revoke them so a stolen token dies with the password change.
        $user->tokens()->delete();

        // Rotate the session ID (fixation hygiene); the login survives.
        $request->session()->regenerate();

        AuditLog::safeRecord(
            $user->school_id, $user->id, 'user.password_changed', 'users', $user->id,
            null, ['ip' => $request->ip()]
        );

        return back()->with('success', 'Password changed. Mobile app sessions were signed out.');
    }
}
