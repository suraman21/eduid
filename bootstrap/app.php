<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role'         => \App\Http\Middleware\RoleMiddleware::class,
            'school.scope' => \App\Http\Middleware\SchoolScope::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Friendly redirect for login rate-limit hits instead of a bare 429 page.
        $exceptions->render(function (
            \Illuminate\Http\Exceptions\ThrottleRequestsException $e,
            \Illuminate\Http\Request $request
        ) {
            if ($request->is('login') || $request->is('admin/login')) {
                $retryAfter = (int) ceil($e->getHeaders()['Retry-After'] ?? 60);
                $minutes    = (int) ceil($retryAfter / 60);
                $msg        = "Too many login attempts. Please wait {$minutes} minute"
                            . ($minutes === 1 ? '' : 's') . ' before trying again.';

                return back()->withErrors(['email' => $msg])->withInput(
                    $request->only('school_code', 'email')
                );
            }
        });
    })->create();
