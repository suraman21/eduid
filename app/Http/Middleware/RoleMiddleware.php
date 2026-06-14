<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-Based Access Control Middleware
 *
 * Usage in routes:
 *   ->middleware('role:super_admin')
 *   ->middleware('role:school_admin,teacher')
 *   ->middleware('role:super_admin,school_admin')
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!auth()->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        $user = auth()->user();

        if (!in_array($user->role, $roles, true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Forbidden. Required role: ' . implode(' or ', $roles),
                ], 403);
            }
            abort(403, 'You do not have permission to access this resource.');
        }

        // Ensure non-super_admin users have an active school
        if ($user->role !== 'super_admin' && $user->school_id) {
            $school = $user->school;
            if (!$school || !$school->is_active) {
                auth()->logout();
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'School account is deactivated.'], 403);
                }
                return redirect()->route('login')->withErrors([
                    'school_code' => 'This school account has been deactivated. Contact Arkeon support.',
                ]);
            }
        }

        // Ensure user account is active
        if (!$user->is_active) {
            auth()->logout();
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Account is deactivated.'], 403);
            }
            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been deactivated. Contact your school administrator.',
            ]);
        }

        return $next($request);
    }
}
