<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admissions: the front door.
 *
 * Until now a student simply appeared when someone typed them into the
 * students table — no record of the enquiry, who took it, what was offered,
 * or why someone was turned away. This holds the pipeline from first contact
 * to enrolment, and keeps rejected and withdrawn applications too, so a
 * school can answer "how many people asked, and how many did we take?".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admission_applications')) {
            return;
        }

        Schema::create('admission_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();

            $table->string('reference', 30);                       // "APP-2026-0007"

            // ── Applicant ──────────────────────────────────────────
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('first_name_am', 100)->nullable();
            $table->string('last_name_am', 100)->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->date('date_of_birth')->nullable();

            $table->string('phone', 30)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('address_line', 255)->nullable();

            // ── Guardian / enquirer ────────────────────────────────
            $table->string('guardian_name', 120)->nullable();
            $table->string('guardian_phone', 30)->nullable();
            $table->string('guardian_email', 120)->nullable();
            $table->string('guardian_relationship', 40)->nullable();

            // ── Where they are coming from ─────────────────────────
            $table->string('previous_school', 150)->nullable();
            $table->string('previous_grade', 40)->nullable();

            // ── What they applied for ──────────────────────────────
            $table->foreignId('programme_id')->nullable()
                ->constrained('programmes')->nullOnDelete();
            $table->foreignId('class_id')->nullable()
                ->constrained('classes')->nullOnDelete();
            $table->string('academic_year', 20)->nullable();
            $table->string('intended_level', 40)->nullable();       // free text when no class exists yet

            // ── Pipeline ───────────────────────────────────────────
            $table->enum('status', [
                'enquiry',     // first contact, not yet a formal application
                'applied',     // submitted, awaiting review
                'screening',   // documents / placement test in progress
                'offered',     // place offered, awaiting answer
                'accepted',    // family accepted, ready to enrol
                'rejected',    // not offered a place
                'withdrawn',   // family withdrew the application
                'converted',   // became a student (see student_id)
            ])->default('applied');

            $table->enum('source', [
                'walk_in', 'phone', 'website', 'referral', 'social', 'event', 'other',
            ])->default('walk_in');

            $table->text('notes')->nullable();
            $table->text('decision_note')->nullable();
            $table->date('follow_up_on')->nullable();

            $table->foreignId('handled_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();

            // ── Conversion ─────────────────────────────────────────
            $table->foreignId('student_id')->nullable()
                ->constrained('students')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();

            $table->timestamps();

            $table->unique(['school_id', 'reference']);
            $table->index(['school_id', 'status'], 'applications_status_idx');
            $table->index(['school_id', 'academic_year'], 'applications_year_idx');
            $table->index(['school_id', 'created_at'], 'applications_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_applications');
    }
};
