<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only timeline of everything that happened to a student.
 *
 * Enrolments answer "where was he in 2026/27?". Events answer "what happened,
 * when, and who did it?" — which is the question a registrar actually asks
 * during a dispute, a transfer, or a graduation audit. Rows are never updated
 * or deleted by the application.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lifecycle_events')) {
            return;
        }

        Schema::create('lifecycle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->nullable()
                ->constrained('student_enrollments')->nullOnDelete();

            $table->enum('event', [
                'admitted', 'enrolled', 'class_changed', 'promoted', 'repeated',
                'transferred_in', 'transferred_out', 'withdrawn', 'suspended',
                'reinstated', 'graduated', 'completed', 'details_updated',
                'document_added',
            ]);

            $table->date('occurred_on');
            $table->string('from_label', 150)->nullable();   // "Grade 8 A · 2025/2026"
            $table->string('to_label', 150)->nullable();     // "Grade 9 B · 2026/2027"
            $table->text('notes')->nullable();

            $table->foreignId('recorded_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['school_id', 'student_id', 'occurred_on'], 'lifecycle_student_date_idx');
            $table->index(['school_id', 'event'], 'lifecycle_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifecycle_events');
    }
};
