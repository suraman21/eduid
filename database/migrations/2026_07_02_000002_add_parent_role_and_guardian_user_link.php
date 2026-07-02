<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parent accounts (PRD Phase 2 "Parent Portal", mobile-first):
 *  - widen users.role with 'parent' (MySQL enum; fresh installs get it from
 *    the base migration, so SQLite tests need no ALTER)
 *  - guardians.user_id links a guardian record to its login account
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','school_admin','teacher','staff','parent') NOT NULL DEFAULT 'teacher'");
        }

        if (! Schema::hasColumn('guardians', 'user_id')) {
            Schema::table('guardians', function (Blueprint $table) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('school_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('guardians', 'user_id')) {
            Schema::table('guardians', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','school_admin','teacher','staff') NOT NULL DEFAULT 'teacher'");
        }
    }
};
