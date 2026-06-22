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

        $this->line('=== DONE ===');

        return self::SUCCESS;
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
