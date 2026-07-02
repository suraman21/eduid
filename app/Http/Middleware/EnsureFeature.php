<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Feature-module gate — the school-panel half of the Packages system.
 *
 * Usage: ->middleware('feature:gradebook')
 *
 * A school only reaches the route when its package includes the module
 * (plan_features for its tier, or a per-school override). Resolution and
 * caching live in School::hasFeature(). Super admins bypass (they are not
 * tenants). The sidebar hides gated modules too, but hiding is UX — this
 * middleware is the enforcement.
 */
class EnsureFeature
{
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $school = $user->school;

        if (! $school || ! $school->hasFeature($featureKey)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This feature is not included in your school\'s package.',
                ], 403);
            }

            abort(403, 'This feature is not included in your school\'s package. Contact Arkeon to upgrade your plan.');
        }

        return $next($request);
    }
}
