<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
    }
}
