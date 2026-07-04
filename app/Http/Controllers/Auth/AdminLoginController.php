<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AdminLoginController extends Controller
{
    /**
     * Show the Super Admin login form.
     */
    public function showLoginForm()
    {
        if (Auth::guard('admin')->check()) {
            return redirect('/admin/dashboard');
        }
        return \Inertia\Inertia::render('Auth/AdminLogin');
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

        // PRD §15: email OTP on every super-admin login. The password step only
        // creates a short-lived pending state; the session is NOT authenticated
        // until the emailed code is verified. Fail-closed: if the code cannot
        // be emailed, the login does not proceed.
        if (config('auth.admin_2fa')) {
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            try {
                Mail::raw(
                    "Hello {$user->name},\n\nYour EduID super-admin verification code is: {$otp}\n\n"
                    ."It expires in 10 minutes. If you did not try to sign in, change your password immediately.\n\n— EduID",
                    fn ($m) => $m->to($user->email)->subject('Your EduID admin verification code')
                );
            } catch (\Throwable $e) {
                Log::error('Admin 2FA email failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                // The mail reason is shown here because this point is only ever
                // reached AFTER a correct super-admin password — so the audience
                // is the real operator, not an attacker — and a generic message
                // makes SMTP misconfiguration impossible to diagnose in the wild.
                throw ValidationException::withMessages([
                    'email' => [
                        'Could not send the verification code (set ADMIN_2FA=false in .env to bypass temporarily). Reason: '
                        . $e->getMessage(),
                    ],
                ]);
            }

            $request->session()->put('admin_2fa', [
                'user_id'    => $user->id,
                'otp_hash'   => Hash::make($otp),
                'expires_at' => now()->addMinutes(10)->timestamp,
                'attempts'   => 0,
                'remember'   => $request->filled('remember'),
            ]);

            return redirect()->route('admin.otp');
        }

        // Dedicated 'admin' guard: coexists with a school login in the same
        // browser session instead of replacing it.
        Auth::guard('admin')->login($user, $request->filled('remember'));

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

    /** Second factor: enter the emailed 6-digit code. */
    public function showOtpForm(Request $request)
    {
        if (! $request->session()->has('admin_2fa')) {
            return redirect()->route('admin.login');
        }

        return \Inertia\Inertia::render('Auth/AdminOtp');
    }

    /** Verify the emailed code and complete the admin login. */
    public function verifyOtp(Request $request)
    {
        $request->validate(['otp' => ['required', 'digits:6']]);

        $state = $request->session()->get('admin_2fa');

        if (! $state || now()->timestamp > $state['expires_at']) {
            $request->session()->forget('admin_2fa');

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'The verification code expired. Please sign in again.']);
        }

        if ($state['attempts'] >= 5) {
            $request->session()->forget('admin_2fa');

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Too many incorrect codes. Please sign in again.']);
        }

        if (! Hash::check($request->input('otp'), $state['otp_hash'])) {
            $state['attempts']++;
            $request->session()->put('admin_2fa', $state);

            throw ValidationException::withMessages(['otp' => ['That code is incorrect.']]);
        }

        $user = User::withoutGlobalScopes()
            ->whereNull('school_id')
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->where('is_active', true)
            ->find($state['user_id']);

        $request->session()->forget('admin_2fa');

        if (! $user) {
            return redirect()->route('admin.login');
        }

        Auth::guard('admin')->login($user, (bool) ($state['remember'] ?? false));
        $request->session()->regenerate();

        AuditLog::safeRecord(
            null, $user->id, 'admin.login_2fa', 'users', $user->id, null,
            ['ip' => $request->ip()]
        );

        return redirect()->intended('/admin/dashboard');
    }

    /**
     * Log the Super Admin out of the ADMIN guard only. The session itself is
     * kept (ID + CSRF token rotated) so a school login in the same browser
     * survives — invalidating the whole session would evict both portals.
     */
    public function logout(Request $request)
    {
        $user = Auth::guard('admin')->user();

        if ($user) {
            AuditLog::safeRecord(
                null, $user->id, 'admin.logout', 'users', $user->id, null,
                ['ip' => $request->ip()]
            );
        }

        Auth::guard('admin')->logout();

        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
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
