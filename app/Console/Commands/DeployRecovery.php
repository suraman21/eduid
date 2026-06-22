<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-shot recovery command for cPanel deployments.
 * Run via cron:
 *   php artisan deploy:recovery >> /home/arkeonet/deploy_recovery.log 2>&1
 *
 * Each step runs independently — a failure in one step does NOT abort the rest.
 */
class DeployRecovery extends Command
{
    protected $signature   = 'deploy:recovery';
    protected $description = 'Clear all caches, fix stale migrations, and report status';

    public function handle(): int
    {
        $this->line('=== DEPLOY RECOVERY ' . now()->toDateTimeString() . ' ===');

        // ── Step 1: Clear all caches FIRST (fixes 419 immediately) ──────────
        $this->step('config:clear',  fn() => Artisan::call('config:clear'));
        $this->step('cache:clear',   fn() => Artisan::call('cache:clear'));
        $this->step('route:clear',   fn() => Artisan::call('route:clear'));
        $this->step('view:clear',    fn() => Artisan::call('view:clear'));

        // ── Step 2: Repair partially-applied migrations ──────────────────────
        $this->repairMigration(
            '2026_06_13_180010_create_card_orders_table',
            'card_orders'
        );
        $this->repairMigration(
            '2026_06_13_180011_create_photo_sessions_table',
            'photo_sessions'
        );

        // ── Step 3: Run any remaining pending migrations ──────────────────────
        $this->step('migrate --force', fn() => Artisan::call('migrate', ['--force' => true]));

        // ── Step 4: Seed feature registry ────────────────────────────────────
        $this->step('db:seed FeatureSeeder', fn() => Artisan::call('db:seed', [
            '--class' => 'FeatureSeeder',
            '--force' => true,
        ]));

        // ── Step 5: storage:link ──────────────────────────────────────────────
        $this->step('storage:link', fn() => Artisan::call('storage:link'));

        // ── Report ────────────────────────────────────────────────────────────
        $this->line('');
        $this->line('=== MIGRATION STATUS ===');
        Artisan::call('migrate:status');
        $this->line(Artisan::output());

        $this->diagnostics();

        $this->line('=== DONE ===');

        return self::SUCCESS;
    }

    /**
     * Capture hard evidence about WHY requests time out: active drivers,
     * DB round-trip latency, table bloat, and the tail of the error log.
     */
    private function diagnostics(): void
    {
        $this->line('');
        $this->line('=== DIAGNOSTICS ===');

        // 1. Which drivers are actually live (after config:clear reads .env)
        $this->line('session.driver = ' . config('session.driver'));
        $this->line('cache.default  = ' . config('cache.default'));
        $this->line('db.default     = ' . config('database.default'));
        $this->line('db.host        = ' . config('database.connections.' . config('database.default') . '.host'));

        // 2. DB round-trip latency — a slow "SELECT 1" means connection trouble
        try {
            $t = microtime(true);
            DB::select('SELECT 1');
            $ms = round((microtime(true) - $t) * 1000, 1);
            $this->line("db.ping        = {$ms} ms");
        } catch (\Throwable $e) {
            $this->line('db.ping        = FAILED: ' . $e->getMessage());
        }

        // 3. MySQL connection ceiling vs current usage (shared-hosting killer)
        try {
            $maxUser = DB::select("SHOW VARIABLES LIKE 'max_user_connections'");
            $maxConn = DB::select("SHOW VARIABLES LIKE 'max_connections'");
            $threads = DB::select("SHOW STATUS LIKE 'Threads_connected'");
            $this->line('max_user_connections = ' . ($maxUser[0]->Value ?? '?'));
            $this->line('max_connections      = ' . ($maxConn[0]->Value ?? '?'));
            $this->line('Threads_connected    = ' . ($threads[0]->Value ?? '?'));
        } catch (\Throwable $e) {
            $this->line('connection stats = FAILED: ' . $e->getMessage());
        }

        // 4. Table bloat — a giant sessions/cache table slows every request
        foreach (['sessions', 'cache', 'cache_locks'] as $tbl) {
            try {
                $count = DB::table($tbl)->count();
                $this->line("rows.{$tbl} = {$count}");
            } catch (\Throwable $e) {
                $this->line("rows.{$tbl} = n/a (" . $e->getMessage() . ')');
            }
        }

        // 5. Stuck cache locks (a held lock blocks requests until it expires)
        try {
            if (Schema::hasTable('cache_locks')) {
                $locks = DB::table('cache_locks')->get();
                foreach ($locks as $lock) {
                    $this->line("LOCK key={$lock->key} expires_at={$lock->expiration}");
                }
            }
        } catch (\Throwable $e) {
            $this->line('cache_locks read = FAILED: ' . $e->getMessage());
        }

        // 6. Tail of the Laravel error log — the actual 500 lives here
        $this->line('');
        $this->line('=== LARAVEL LOG (last 60 lines) ===');
        $logPath = storage_path('logs/laravel.log');
        if (is_file($logPath)) {
            $lines = @file($logPath, FILE_IGNORE_NEW_LINES) ?: [];
            foreach (array_slice($lines, -60) as $l) {
                $this->line($l);
            }
        } else {
            $this->line('(no laravel.log found at ' . $logPath . ')');
        }
    }

    private function step(string $name, callable $fn): void
    {
        try {
            $fn();
            $output = trim(Artisan::output());
            $this->line("[OK]  {$name}" . ($output ? ": {$output}" : ''));
        } catch (\Throwable $e) {
            $this->line("[ERR] {$name}: " . $e->getMessage());
        }
    }

    /**
     * If the table exists in MySQL but is NOT in the migrations tracking table,
     * the standard `migrate` command aborts with "table already exists".
     * This method detects and repairs that split-brain state.
     */
    private function repairMigration(string $migrationName, string $tableName): void
    {
        try {
            $tableExists     = Schema::hasTable($tableName);
            $migrationExists = DB::table('migrations')
                ->where('migration', $migrationName)
                ->exists();

            if ($tableExists && !$migrationExists) {
                DB::table('migrations')->insert([
                    'migration' => $migrationName,
                    'batch'     => DB::table('migrations')->max('batch') + 1,
                ]);
                $this->line("[FIX] Recorded missing migration: {$migrationName}");
            } elseif (!$tableExists && $migrationExists) {
                // Recorded but table missing — remove stale record so migrate can re-run it
                DB::table('migrations')->where('migration', $migrationName)->delete();
                $this->line("[FIX] Removed stale migration record: {$migrationName} (table missing, will re-create)");
            } else {
                $state = $tableExists ? 'table + record both present' : 'neither — will be created by migrate';
                $this->line("[OK]  {$migrationName}: {$state}");
            }
        } catch (\Throwable $e) {
            $this->line("[ERR] repairMigration({$migrationName}): " . $e->getMessage());
        }
    }
}
