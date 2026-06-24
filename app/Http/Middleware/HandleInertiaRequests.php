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
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
        ]);
    }
}
