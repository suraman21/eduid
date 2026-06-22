<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Show the school login form.
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return $this->redirectUser(Auth::user());
        }
        return view('auth.login');
    }

    /**
     * Handle a school login request.
     */
    public function login(Request $request)
    {
        $request->validate([
            'school_code' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Find the school
        $school = School::where('code', $request->school_code)->first();
        if (!$school) {
            throw ValidationException::withMessages([
                'school_code' => ['The provided school code does not exist.'],
            ]);
        }

        if (!$school->is_active) {
            throw ValidationException::withMessages([
                'school_code' => ['This school account is currently deactivated.'],
            ]);
        }

        // Find the user within the school
        // Skip global tenant scope temporarily to query by school_id directly
        $user = User::withoutGlobalScopes()
            ->where('school_id', $school->id)
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
                'email' => ['Your account is currently deactivated. Please contact your administrator.'],
            ]);
        }

        // Respect the can_login flag (admin can revoke portal access without
        // deactivating the user account entirely).
        if (!$user->can_login) {
            throw ValidationException::withMessages([
                'email' => ['Your portal access has been disabled. Please contact your administrator.'],
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
            $user->school_id,
            $user->id,
            'user.login',
            'users',
            $user->id,
            null,
            ['ip' => $request->ip(), 'user_agent' => $request->userAgent()]
        );

        $request->session()->regenerate();

        // Put school_id in session for SchoolScope middleware validation
        $request->session()->put('school_id', $user->school_id);

        return $this->redirectUser($user);
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
                $user->school_id,
                $user->id,
                'user.lockout',
                'users',
                $user->id,
                null,
                ['ip' => request()->ip(), 'reason' => '5 consecutive failed login attempts']
            );
        }
    }

    /**
     * Redirect the user based on their role.
     */
    protected function redirectUser($user)
    {
        if ($user->isSuperAdmin()) {
            return redirect()->intended('/admin/dashboard');
        }

        return redirect()->intended('/school/dashboard');
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request)
    {
        $user = Auth::user();

        if ($user) {
            AuditLog::safeRecord(
                $user->school_id,
                $user->id,
                'user.logout',
                'users',
                $user->id,
                null,
                ['ip' => $request->ip()]
            );
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
