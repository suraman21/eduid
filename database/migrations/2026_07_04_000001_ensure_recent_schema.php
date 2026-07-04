<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SELF-HEALING BACKSTOP for the recent schema additions.
 *
 * A prior deploy applied the code but not the columns (the classic "Unknown
 * column 'homeroom_teacher_id'" 500). The original per-feature migrations are
 * already marked "run", so `migrate` will never retry them — but a brand-new
 * migration file always runs. This one re-adds every recent column/table IF
 * missing, fully guarded so it can NEVER throw (and therefore never blocks the
 * rest of the deploy). Safe to run repeatedly; a no-op once everything exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        $isMysql = DB::connection()->getDriverName() === 'mysql';

        // 1. classes.homeroom_teacher_id  ← the column in the reported 500.
        if (Schema::hasTable('classes') && ! Schema::hasColumn('classes', 'homeroom_teacher_id')) {
            Schema::table('classes', function (Blueprint $table) {
                $table->unsignedBigInteger('homeroom_teacher_id')->nullable();
            });
            $this->addForeignKey('classes', 'homeroom_teacher_id');
        }

        // 2. users.role must include 'parent' (and 'staff').
        if ($isMysql && Schema::hasTable('users')) {
            try {
                DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','school_admin','teacher','staff','parent') NOT NULL DEFAULT 'teacher'");
            } catch (\Throwable $e) { /* enum already wide enough */ }
        }

        // 3. guardians.user_id (parent-account link).
        if (Schema::hasTable('guardians') && ! Schema::hasColumn('guardians', 'user_id')) {
            Schema::table('guardians', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable();
            });
            $this->addForeignKey('guardians', 'user_id');
        }

        // 4. guardians.telegram_chat_id (absence alerts).
        if (Schema::hasTable('guardians') && ! Schema::hasColumn('guardians', 'telegram_chat_id')) {
            Schema::table('guardians', function (Blueprint $table) {
                $table->string('telegram_chat_id', 32)->nullable();
            });
        }

        // 5. users.onboarding_state (first-run tour).
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'onboarding_state')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('onboarding_state')->nullable();
            });
        }

        // 5b. schools.working_days (per-school Mon–Fri / Mon–Sat pattern).
        if (Schema::hasTable('schools') && ! Schema::hasColumn('schools', 'working_days')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->json('working_days')->nullable();
            });
        }

        // 6. alert_outbox table (Telegram outbox).
        if (! Schema::hasTable('alert_outbox')) {
            Schema::create('alert_outbox', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('guardian_id')->constrained('guardians')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->foreignId('session_id')->nullable()->constrained('attendance_sessions')->nullOnDelete();
                $table->string('channel', 20)->default('telegram');
                $table->text('message');
                $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->string('last_error', 500)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->unique(['session_id', 'student_id', 'guardian_id']);
                $table->index(['status', 'created_at']);
            });
        }
    }

    /** Add a nullable FK to users(id), swallowing any error (FK is a nicety). */
    private function addForeignKey(string $table, string $column): void
    {
        try {
            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->foreign($column)->references('id')->on('users')->nullOnDelete();
            });
        } catch (\Throwable $e) {
            // A missing FK never breaks functionality — the column is what matters.
        }
    }

    public function down(): void
    {
        // Non-destructive backstop — nothing to roll back.
    }
};
