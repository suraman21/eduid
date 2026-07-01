<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Detects schema drift between the code and the database — i.e. deploys that
 * skipped `php artisan migrate`. That drift has repeatedly caused
 * "Base table … doesn't exist" 500s in production; surfacing it as an explicit
 * admin warning (and in /health) turns a mystery crash into a one-line fix.
 */
class SchemaHealth
{
    /**
     * Number of migration files that have not been recorded as run.
     * Returns -1 when it cannot be determined (e.g. no DB connection).
     */
    public static function pendingMigrations(): int
    {
        try {
            $ran = DB::table('migrations')->pluck('migration')->flip();
            $pending = 0;
            foreach (glob(database_path('migrations/*.php')) ?: [] as $file) {
                if (! isset($ran[basename($file, '.php')])) {
                    $pending++;
                }
            }

            return $pending;
        } catch (\Throwable) {
            return -1;
        }
    }
}
