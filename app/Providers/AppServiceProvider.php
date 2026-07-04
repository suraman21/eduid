<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiters();
        $this->configureSmtpTransport();
    }

    /**
     * cPanel-friendly SMTP transport.
     *
     * Two things bite shared-hosting mailboxes and both are handled here:
     *
     *  1. Port 465 is *implicit* SSL (scheme "smtps"). Laravel 11 dropped
     *     MAIL_ENCRYPTION and derives the scheme from MAIL_SCHEME only, so a
     *     .env with just MAIL_PORT=465 can end up trying plaintext on an SSL
     *     port. We pin the scheme from the port so 465→smtps, else→smtp.
     *
     *  2. cPanel mailboxes routinely present a shared or self-signed TLS
     *     certificate whose CN does not match the mail hostname, which makes
     *     the SSL handshake fail peer verification. MAIL_VERIFY_PEER=false
     *     relaxes that check — safe here because the app sends through the very
     *     same server that hosts the mailbox.
     *
     * Only the "smtp" mailer is touched; log/array/fake transports (tests,
     * local dev) are untouched.
     */
    private function configureSmtpTransport(): void
    {
        Mail::extend('smtp', function (array $config) {
            $scheme = $config['scheme'] ?? null;
            if (! $scheme) {
                $scheme = ((int) ($config['port'] ?? 0)) === 465 ? 'smtps' : 'smtp';
            }

            $transport = (new EsmtpTransportFactory)->create(new Dsn(
                $scheme,
                $config['host'] ?? '127.0.0.1',
                $config['username'] ?? null,
                $config['password'] ?? null,
                $config['port'] ?? null,
                $config
            ));

            if (($config['verify_peer'] ?? true) === false) {
                $stream = $transport->getStream();
                if ($stream instanceof SocketStream) {
                    $stream->setStreamOptions([
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true,
                        ],
                    ]);
                }
            }

            return $transport;
        });
    }

    /**
     * Named rate limiters (PRD §08). Named limiters keep each route's counter
     * isolated — bare inline throttles on authenticated routes would otherwise
     * share one bucket per user and interfere with each other.
     */
    private function configureRateLimiters(): void
    {
        // 5 login attempts per 15 minutes, per IP + email.
        RateLimiter::for('login', fn (Request $r) => Limit::perMinutes(15, 5)
            ->by($r->ip() . '|' . strtolower((string) $r->input('email'))));

        // CSV import: 3 per hour, per school.
        RateLimiter::for('csv-import', fn (Request $r) => Limit::perHour(3)
            ->by('csv|' . (optional($r->user())->school_id ?: $r->ip())));

        // Card order creation: 5 per hour, per school.
        RateLimiter::for('card-order', fn (Request $r) => Limit::perHour(5)
            ->by('order|' . (optional($r->user())->school_id ?: $r->ip())));

        // Photo upload: 30 per minute, per user.
        RateLimiter::for('photo-upload', fn (Request $r) => Limit::perMinute(30)
            ->by('photo|' . (optional($r->user())->id ?: $r->ip())));

        // Mobile API (Flutter): 300 requests/minute per authenticated user.
        // Generous enough for rapid scanning + bulk sync, but caps a leaked
        // token hammering verify-qr / sync. Keyed per user (IP pre-auth).
        RateLimiter::for('mobile', fn (Request $r) => Limit::perMinute(300)
            ->by('mob|' . (optional($r->user())->id ?: $r->ip())));

        // Public contact / lead form: 5 submissions per 10 minutes, per IP —
        // blunts lead-spam and DB flooding on an unauthenticated endpoint.
        RateLimiter::for('contact', fn (Request $r) => Limit::perMinutes(10, 5)
            ->by('contact|' . $r->ip()));
    }
}
