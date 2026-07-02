<?php

namespace App\Http\Controllers\School;

use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Parent app logins for guardian records (PRD "Parent Portal", mobile-first).
 *
 * Credential model follows PRD §08's admin-hierarchy reset: the school admin
 * creates the account, gets a ONE-TIME temporary password to hand to the
 * parent (paper/WhatsApp — email delivery is unreliable in-market), and can
 * reset or revoke it any time. Parents sign in only via the mobile app.
 *
 * Route ring: school_admin only; the school itself must have the
 * 'parent_app' module (feature:parent_app on the route group).
 */
class GuardianAccountController extends BaseSchoolController
{
    /** Create a login for a guardian, or reset its password. */
    public function store(Request $request, Guardian $guardian)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $validated = $request->validate([
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users')->where('school_id', $this->schoolId())
                    ->ignore($guardian->user_id),
            ],
        ]);

        // 10 chars, unambiguous alphabet — readable over the phone.
        $tempPassword = Str::password(10, letters: true, numbers: true, symbols: false);

        if ($guardian->user_id && $guardian->user) {
            $guardian->user->update([
                'email'    => $validated['email'],
                'password' => Hash::make($tempPassword),
                'is_active' => true,
                'can_login' => true,
            ]);
            // A reset kills every existing app session for this parent.
            $guardian->user->tokens()->delete();
            $user = $guardian->user;
            $action = 'guardian.account_reset';
            $verb = 'reset';
        } else {
            $user = User::create([
                'school_id' => $this->schoolId(),
                'name'      => $guardian->full_name,
                'email'     => $validated['email'],
                'password'  => Hash::make($tempPassword),
                'role'      => User::ROLE_PARENT,
                'phone'     => $guardian->phone,
                'is_active' => true,
                'can_login' => true,
            ]);
            $guardian->update(['user_id' => $user->id, 'email' => $validated['email']]);
            $action = 'guardian.account_created';
            $verb = 'created';
        }

        AuditLog::log($action, 'users', $user->id, null, [
            'guardian_id' => $guardian->id, 'email' => $validated['email'],
        ]);

        // Shown ONCE via flash — never stored or logged in plain text.
        return back()->with('success',
            "Parent app access {$verb} for {$guardian->full_name}. Temporary password: {$tempPassword} — share it now, it will not be shown again. They sign in on the EduID app with school code " . $this->currentSchool()->code . '.');
    }

    /** Revoke app access (keeps the guardian record itself). */
    public function destroy(Guardian $guardian)
    {
        $this->requireAdmin();

        if ($guardian->user_id && $guardian->user) {
            $guardian->user->tokens()->delete();
            $guardian->user->update(['is_active' => false, 'can_login' => false]);

            AuditLog::log('guardian.account_revoked', 'users', $guardian->user_id, null, [
                'guardian_id' => $guardian->id,
            ]);
        }

        return back()->with('success', "App access revoked for {$guardian->full_name}.");
    }
}
