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
            'feature'      => \App\Http\Middleware\EnsureFeature::class,
        ]);

        // PRD §10: strict CSP + security headers on all web responses.
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
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

        // Expired CSRF token (session timed out, or was rotated by a login in
        // another tab). A sign-out attempt should simply land on the right
        // login — the user's intent was to leave anyway. Everything else gets
        // the custom EDU-419 page instead of Laravel's bare "Page Expired".
        // (The framework prepares TokenMismatchException into HttpException(419)
        // before render callbacks run, so we match on the prepared form.)
        $exceptions->render(function (
            \Symfony\Component\HttpKernel\Exception\HttpException $e,
            \Illuminate\Http\Request $request
        ) {
            if ($e->getStatusCode() === 419
                && ($request->is('logout') || $request->is('admin/logout'))
            ) {
                return redirect($request->is('admin/logout') ? '/admin/login' : '/login');
            }

            return null; // falls through to resources/views/errors/419.blade.php
        });

        // Per-incident support reference for unexpected server errors. The code
        // shown to the user (EDU-ERR-XXXXXXXX) is logged with the full exception
        // + request context, so a screenshot from a school maps straight to the
        // exact log line. HTTP/validation/auth exceptions keep their dedicated
        // handling; JSON clients keep JSON; debug mode keeps the debug page.
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (config('app.debug')
                || $request->expectsJson()
                || $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                || $e instanceof \Illuminate\Validation\ValidationException
                || $e instanceof \Illuminate\Auth\AuthenticationException
                || $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || $e instanceof \Illuminate\Session\TokenMismatchException
            ) {
                return null;
            }

            $ref = 'EDU-ERR-'.strtoupper(bin2hex(random_bytes(4)));

            \Illuminate\Support\Facades\Log::error("[{$ref}] ".$e->getMessage(), [
                'exception' => $e,
                'url'       => $request->fullUrl(),
                'method'    => $request->method(),
                'user_id'   => auth()->id(),
                'ip'        => $request->ip(),
            ]);

            return response()->view('errors.500', ['ref' => $ref], 500);
        });
    })->create();
