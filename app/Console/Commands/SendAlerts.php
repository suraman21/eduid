<?php

namespace App\Console\Commands;

use App\Services\AlertService;
use Illuminate\Console\Command;

/**
 * Flush the alert outbox. The shared host has no queue workers, so this is
 * the retry path for anything the inline best-effort flush didn't deliver.
 *
 * cPanel cron (every 5 minutes):
 *   /opt/cpanel/ea-php84/root/usr/bin/php artisan alerts:send
 */
class SendAlerts extends Command
{
    protected $signature = 'alerts:send {--limit=200 : Max messages this run}';

    protected $description = 'Send pending Telegram absence alerts from the outbox';

    public function handle(AlertService $alerts): int
    {
        [$sent, $failed] = $alerts->flush(null, (int) $this->option('limit'));

        $this->info("Alerts flushed: {$sent} sent, {$failed} failed/deferred.");

        return self::SUCCESS;
    }
}
