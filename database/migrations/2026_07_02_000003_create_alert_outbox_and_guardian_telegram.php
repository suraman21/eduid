<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telegram absence alerts (PRD Phase-2 "SMS / Telegram Alerts" module).
 *
 * No queue workers exist on the shared host, so alerts flow through an
 * OUTBOX table: enqueued at attendance save, flushed opportunistically in
 * the same request (best effort, short timeout) and by the alerts:send
 * artisan command from cron. The unique (session, student, guardian) key
 * makes enqueueing idempotent — re-saving attendance never re-spams.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('guardians', 'telegram_chat_id')) {
            Schema::table('guardians', function (Blueprint $table) {
                $table->string('telegram_chat_id', 32)->nullable()->after('email');
            });
        }

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

    public function down(): void
    {
        Schema::dropIfExists('alert_outbox');

        if (Schema::hasColumn('guardians', 'telegram_chat_id')) {
            Schema::table('guardians', function (Blueprint $table) {
                $table->dropColumn('telegram_chat_id');
            });
        }
    }
};
