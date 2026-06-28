<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * A valid bcrypt hash used only to normalise login timing when no matching
     * user exists, so an attacker cannot tell (via response time) whether an
     * email is registered for a school. It never matches any real password.
     */
    private const TIMING_HASH = '$2y$12$97I7wAJnTzR1.oqSUdEtTOSQYZVN4RuXxYQd2h0tR5VwFs72tWDO.';

    /**
     * Handle API login and issue Sanctum token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'school_code' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
        ]);

        // Find the school
        $school = School::where('code', $request->school_code)->first();
        if (!$school) {
            return response()->json([
                'message' => 'The provided school code does not exist.'
            ], 404);
        }

        if (!$school->is_active) {
            return response()->json([
                'message' => 'This school account is currently deactivated.'
            ], 403);
        }

        // Find the user within the school
        $user = User::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('email', $request->email)
            ->first();

        if (!$user) {
            // Run a dummy verify so the no-such-user path costs the same as a
            // real bcrypt check — closes the timing side-channel for email
            // enumeration. (Messages are already identical to the wrong-password
            // path below.)
            Hash::check($request->password, self::TIMING_HASH);

            return response()->json([
                'message' => 'These credentials do not match our records.'
            ], 401);
        }

        // Check account status
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Your account is currently deactivated. Please contact your administrator.'
            ], 403);
        }

        // Check for account lockout (uses model method)
        if ($user->isLocked()) {
            $diffInMinutes = ceil(now()->diffInMinutes($user->locked_until));
            return response()->json([
                'message' => "Too many failed login attempts. Your account is locked. Please try again in {$diffInMinutes} minutes."
            ], 429);
        }

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            $this->handleFailedLogin($user);
            return response()->json([
                'message' => 'These credentials do not match our records.'
            ], 401);
        }

        // Clear lockouts on successful login (uses model method)
        $user->resetFailedLogins();

        $deviceName = $request->device_name ?? 'mobile-app';
        $token = $user->createToken($deviceName)->plainTextToken;

        // Log successful login (safe — never crashes the login)
        AuditLog::safeRecord(
            $user->school_id,
            $user->id,
            'api.login',
            'users',
            $user->id,
            null,
            ['ip' => $request->ip(), 'device_name' => $deviceName]
        );

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'code' => $school->code,
                'calendar_system' => $school->calendar_system,
                'academic_year' => $school->academic_year,
            ]
        ], 200);
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
                'api.lockout',
                'users',
                $user->id,
                null,
                ['ip' => request()->ip(), 'reason' => '5 consecutive failed login attempts']
            );
        }
    }

    /**
     * Get the authenticated user info.
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $school = School::find($user->school_id);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'school' => $school ? [
                'id' => $school->id,
                'name' => $school->name,
                'code' => $school->code,
                'calendar_system' => $school->calendar_system,
                'academic_year' => $school->academic_year,
            ] : null
        ], 200);
    }

    /**
     * Log the user out (revoke current token).
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            // Revoke current token
            $user->currentAccessToken()->delete();

            AuditLog::safeRecord(
                $user->school_id,
                $user->id,
                'api.logout',
                'users',
                $user->id,
                null,
                ['ip' => $request->ip()]
            );
        }

        return response()->json([
            'message' => 'Logged out successfully.'
        ], 200);
    }
}
