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
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [
                    'id'        => $request->user()->id,
                    'name'      => $request->user()->name,
                    'email'     => $request->user()->email,
                    'role'      => $request->user()->role,
                    'school_id' => $request->user()->school_id,
                ] : null,
            ],
            'school' => $request->user()?->school ? [
                'name'         => $request->user()->school->name,
                'code'         => $request->user()->school->code,
                'accent_color' => $request->user()->school->accent_color,
                'plan_tier'    => $request->user()->school->plan_tier,
            ] : null,
            // Feature-module flags for the school panel (sidebar hiding is
            // UX; the 'feature:*' route middleware is the enforcement). Only
            // the keys the frontend actually branches on — resolved through
            // School::hasFeature() which caches per school+key.
            'features' => $request->user()?->school ? [
                'gradebook'    => $request->user()->school->hasFeature('gradebook'),
                'report_cards' => $request->user()->school->hasFeature('report_cards'),
                'csv_import'      => $request->user()->school->hasFeature('csv_import'),
                'timetable'       => $request->user()->school->hasFeature('timetable'),
                'year_transition' => $request->user()->school->hasFeature('year_transition'),
                'analytics'       => $request->user()->school->hasFeature('analytics'),
                'alerts'          => $request->user()->school->hasFeature('alerts'),
                'fees'            => $request->user()->school->hasFeature('fees'),
            ] : null,
            // Flat, top-level accent colour. Kept SEPARATE from `school` on
            // purpose: a page that returns its own `school` prop (e.g. the
            // dashboard, which carries page-specific fields) shadows the shared
            // `school` and would otherwise drop accent_color, making the layout
            // fall back to the default indigo. `accent` is never passed by a
            // page controller, so it is always present and drives the whole
            // school-panel chrome consistently. Normalised to a valid 6-digit
            // hex with an indigo fallback.
            'accent' => $request->user()?->school?->accent_color
                && preg_match('/^#[0-9a-fA-F]{6}$/', $request->user()->school->accent_color)
                    ? $request->user()->school->accent_color
                    : '#6366f1',
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
            // Onboarding tour state + LIVE progress signals (school users
            // only, and only while onboarding is unfinished — zero query
            // overhead once complete).
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
