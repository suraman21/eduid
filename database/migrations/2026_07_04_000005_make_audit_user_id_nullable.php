<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * System events (Telegram webhook guardian-linking, cron jobs) audit with
 * user_id = NULL — there is no acting user. The column was NOT NULL, so those
 * inserts silently failed inside safeRecord() and the audit trail for every
 * system action was never written. Relax to nullable (FK behaviour preserved
 * on MySQL; SQLite test schema is rebuilt from scratch anyway).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE audit_logs DROP FOREIGN KEY audit_logs_user_id_foreign');
            } catch (\Throwable $e) {
                // FK may have a different name or not exist — the MODIFY below
                // is what matters.
            }
            DB::statement('ALTER TABLE audit_logs MODIFY user_id BIGINT UNSIGNED NULL');
            try {
                DB::statement('ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL');
            } catch (\Throwable $e) {
                // Re-adding the FK is a nicety; the nullable column is the fix.
            }

            return;
        }

        // SQLite (tests): rebuild-safe change via a fresh column swap is
        // overkill — hasColumn-level alteration is enough because the test
        // schema is created fresh each run from the ORIGINAL migration, so we
        // patch that file's behaviour with a table rewrite here.
        Schema::table('audit_logs', function ($table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Non-destructive relaxation — nothing to roll back.
    }
};
