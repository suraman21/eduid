<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * OTP-based password reset for school portal users.
 *
 * Flow:
 *   1. User submits school code + email.
 *   2. A 6-digit code is generated, hashed, stored with a 10-minute expiry,
 *      and emailed to the address on file.
 *   3. User submits the code + a new password; on a valid, unexpired match the
 *      password is updated and the code consumed.
 *
 * To avoid leaking which (school, email) pairs exist, the request step always
 * reports success regardless of whether a matching user was found.
 */
class PasswordResetController extends Controller
{
    private const OTP_TTL_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Generate and email a one-time code. Always returns a neutral success
     * message so the form cannot be used to enumerate accounts.
     */
    public function sendOtp(Request $request)
    {
        $data = $request->validate([
            'school_code' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
        ]);

        $school = School::where('code', $data['school_code'])->where('is_active', true)->first();

        $user = $school
            ? User::withoutGlobalScopes()
                ->where('school_id', $school->id)
                ->where('email', $data['email'])
                ->first()
            : null;

        if ($school && $user && $user->is_active && $user->can_login) {
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // One active code per (school, email): clear any prior codes first.
            DB::table('password_reset_otps')
                ->where('school_id', $school->id)
                ->where('email', $user->email)
                ->delete();

            DB::table('password_reset_otps')->insert([
                'school_id' => $school->id,
                'email' => $user->email,
                'otp_hash' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
                'created_at' => now(),
            ]);

            $this->emailOtp($user, $school, $otp);

            AuditLog::safeRecord(
                $school->id,
                $user->id,
                'user.password_reset_requested',
                'users',
                $user->id,
                null,
                ['ip' => $request->ip()]
            );
        }

        // Carry the identifiers to the reset screen so the user only enters the
        // code + new password. These alone grant nothing without the emailed OTP.
        return redirect()
            ->route('password.reset', [
                'school_code' => $data['school_code'],
                'email' => $data['email'],
            ])
            ->with('status', 'If an account matches those details, a 6-digit reset code has been emailed to it. The code expires in '.self::OTP_TTL_MINUTES.' minutes.');
    }

    public function showResetForm(Request $request)
    {
        return view('auth.reset-password', [
            'school_code' => (string) $request->query('school_code', ''),
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /**
     * Verify the code and set the new password.
     */
    public function reset(Request $request)
    {
        $data = $request->validate([
            'school_code' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'otp' => ['required', 'string', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $school = School::where('code', $data['school_code'])->where('is_active', true)->first();
        if (! $school) {
            throw ValidationException::withMessages(['otp' => ['This reset code is invalid or has expired.']]);
        }

        $record = DB::table('password_reset_otps')
            ->where('school_id', $school->id)
            ->where('email', $data['email'])
            ->orderByDesc('id')
            ->first();

        if (! $record || now()->greaterThan($record->expires_at)) {
            throw ValidationException::withMessages(['otp' => ['This reset code is invalid or has expired.']]);
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            DB::table('password_reset_otps')->where('id', $record->id)->delete();
            throw ValidationException::withMessages(['otp' => ['Too many incorrect attempts. Please request a new code.']]);
        }

        if (! Hash::check($data['otp'], $record->otp_hash)) {
            DB::table('password_reset_otps')->where('id', $record->id)->increment('attempts');
            throw ValidationException::withMessages(['otp' => ['This reset code is invalid or has expired.']]);
        }

        $user = User::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('email', $data['email'])
            ->first();

        if (! $user) {
            throw ValidationException::withMessages(['otp' => ['This reset code is invalid or has expired.']]);
        }

        $user->update([
            'password' => Hash::make($data['password']),
            'failed_login_count' => 0,
            'locked_until' => null,
        ]);

        // Consume every code for this identity once the reset succeeds.
        DB::table('password_reset_otps')
            ->where('school_id', $school->id)
            ->where('email', $data['email'])
            ->delete();

        AuditLog::safeRecord(
            $school->id,
            $user->id,
            'user.password_reset',
            'users',
            $user->id,
            null,
            ['ip' => $request->ip()]
        );

        return redirect()->route('login')
            ->with('reset_status', 'Your password has been reset. You can now sign in with your new password.');
    }

    /**
     * Send the OTP email. Failures are logged but never surfaced to the user
     * (the neutral success response must not change if mail is misconfigured).
     */
    private function emailOtp(User $user, School $school, string $otp): void
    {
        try {
            Mail::raw(
                "Hello {$user->name},\n\n"
                ."We received a request to reset your EduID password for {$school->name}.\n\n"
                ."Your reset code is: {$otp}\n\n"
                .'This code expires in '.self::OTP_TTL_MINUTES.' minutes. '
                ."If you did not request this, you can safely ignore this email.\n\n"
                .'— EduID',
                function ($message) use ($user, $school) {
                    $message->to($user->email)
                        ->subject("Your {$school->name} password reset code");
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Password reset OTP email failed', [
                'school_id' => $school->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
