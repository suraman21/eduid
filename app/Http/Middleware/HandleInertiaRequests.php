<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        // Resolve the user and school ONCE (each `$request->user()` is a
        // resolver call, and every `->school` before this walked the relation).
        $user   = $request->user();
        $school = $user?->school;
        $accent = $school?->accent_color
            && preg_match('/^#[0-9a-fA-F]{6}$/', $school->accent_color)
                ? $school->accent_color
                : '#6366f1';

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id'        => $user->id,
                    'name'      => $user->name,
                    'email'     => $user->email,
                    'role'      => $user->role,
                    'school_id' => $user->school_id,
                ] : null,
            ],
            'school' => $school ? [
                'name'         => $school->name,
                'code'         => $school->code,
                'accent_color' => $school->accent_color,
                'plan_tier'    => $school->plan_tier,
            ] : null,
            // Feature-module flags for the school panel (sidebar hiding is UX;
            // the 'feature:*' route middleware is the enforcement). Resolved in
            // ONE cached read via School::frontendFeatures() — see that method.
            'features' => $school?->frontendFeatures(),
            // Flat, top-level accent colour. Kept SEPARATE from `school` on
            // purpose: a page that returns its own `school` prop (e.g. the
            // dashboard) shadows the shared `school` and would otherwise drop
            // accent_color. `accent` is never passed by a page controller, so
            // it is always present and drives the whole school-panel chrome.
            'accent' => $accent,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
            // Onboarding tour state (school users only). Zero queries.
            'onboarding' => $this->onboarding($request),
        ]);
    }

    /**
     * Onboarding payload for the school portal: persisted UI flags plus
     * cheap, tenant-scoped existence checks that drive the gamified
     * checklist. Returns null for super admins and guests.
     */
    private function onboarding(Request $request): ?array
    {
        $user = $request->user();

        if (! $user
            || ! in_array($user->role, ['school_admin', 'teacher', 'staff'], true)
            || ! $user->school_id) {
            return null;
        }

        $state = $user->onboarding_state ?? [];

        // Just the welcome-tour flags. The tour is a guided walkthrough only —
        // it never nags the user to complete tasks that aren't their job, and
        // it costs zero queries.
        return [
            'tour_seen' => (bool) ($state['tour_seen'] ?? false),
            'dismissed' => (bool) ($state['dismissed'] ?? false),
        ];
    }
}
