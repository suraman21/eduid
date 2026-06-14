<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminLoginController extends Controller
{
    /**
     * Show the Super Admin login form.
     */
    public function showLoginForm()
    {
        if (Auth::check() && Auth::user()->isSuperAdmin()) {
            return redirect('/admin/dashboard');
        }
        return view('auth.admin-login');
    }

    /**
     * Handle a Super Admin login request.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Find the Super Admin user (school_id must be null and role must be super_admin)
        $user = User::withoutGlobalScopes()
            ->whereNull('school_id')
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->where('email', $request->email)
            ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        // Check account status
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account is deactivated.'],
            ]);
        }

        // Check for account lockout (uses model method)
        if ($user->isLocked()) {
            $diffInMinutes = ceil(now()->diffInMinutes($user->locked_until));
            throw ValidationException::withMessages([
                'email' => ["Too many failed login attempts. Your account is locked. Please try again in {$diffInMinutes} minutes."],
            ]);
        }

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            $this->handleFailedLogin($user);
            
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        // Clear lockouts on successful login (uses model method)
        $user->resetFailedLogins();

        Auth::login($user, $request->filled('remember'));

        // Log successful login (safe — never crashes the login)
        AuditLog::safeRecord(
            null, // Super Admin doesn't belong to a school
            $user->id,
            'admin.login',
            'users',
            $user->id,
            null,
            ['ip' => $request->ip(), 'user_agent' => $request->userAgent()]
        );

        $request->session()->regenerate();

        return redirect()->intended('/admin/dashboard');
    }

    /**
     * Handle failed login attempt and check for lockouts.
     */
    protected function handleFailedLogin(User $user)
    {
        // Uses model method — increments count, locks if threshold reached
        $user->incrementFailedLogins();

        // Log lockout event if account just got locked
        if ($user->isLocked()) {
            AuditLog::safeRecord(
                null,
                $user->id,
                'admin.lockout',
                'users',
                $user->id,
                null,
                ['ip' => request()->ip(), 'reason' => '5 consecutive failed login attempts']
            );
        }
    }
}
