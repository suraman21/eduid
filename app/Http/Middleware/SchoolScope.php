<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SchoolScope Middleware
 *
 * Ensures the authenticated user has a valid school_id set in session.
 * Used on all school-facing web routes to guarantee tenant isolation.
 * Super Admin bypasses this check.
 */
class SchoolScope
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Super Admin bypasses school scope
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        // Non-super_admin users must have a school_id
        if (!$user->school_id) {
            auth()->logout();
            return redirect()->route('login')->withErrors([
                'email' => 'Your account is not associated with any school.',
            ]);
        }

        // Share school data with all views
        $school = $user->school;
        if ($school) {
            view()->share('currentSchool', $school);
            view()->share('calendarSystem', $school->calendar_system);
        }

        return $next($request);
    }
}
