<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the PRD §10 security headers, including a strict Content-Security-Policy.
 *
 * A per-request nonce is generated and shared with Blade (via View::share in the
 * AppServiceProvider boot) so that the handful of legitimate inline <script>
 * blocks can opt in with nonce="{{ $cspNonce }}". Everything else — external
 * scripts, inline event handlers — is blocked.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generate the per-request nonce up front and share it with every view
        // so legitimate inline <script> blocks can opt in via nonce="{{ $cspNonce }}".
        $nonce = Str::random(24);
        View::share('cspNonce', $nonce);

        /** @var Response $response */
        $response = $next($request);

        // Don't rewrite headers on streamed file downloads (photo serving etc.)
        // or binary responses — a CSP on an image is meaningless and the
        // BinaryFileResponse may not carry a mutable header bag the same way.
        $contentType = (string) $response->headers->get('Content-Type');
        $isHtml = $contentType === '' || Str::startsWith($contentType, 'text/html');

        if ($isHtml) {
            $response->headers->set('Content-Security-Policy', $this->csp($nonce));
        }

        // Headers that are safe on every response.
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-XSS-Protection', '0'); // CSP supersedes the legacy filter
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // HSTS only over HTTPS (sending it over plain HTTP is ignored anyway,
        // but we gate it to avoid surprises behind local/proxy setups).
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * The Content-Security-Policy. Matches PRD §10:
     *   default-src 'self'; script-src 'self'; img-src 'self' data:;
     *   style-src 'self' 'unsafe-inline'
     * with a nonce added to script-src so server-rendered inline scripts work.
     */
    private function csp(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
