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
        ]);
    }
}
