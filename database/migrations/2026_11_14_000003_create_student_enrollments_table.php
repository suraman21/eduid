<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The lifecycle spine.
 *
 * Until now a student's class lived in a single mutable column
 * (students.class_id), so every promotion overwrote the previous year and no
 * history survived. An enrolment row is an *immutable fact*: "this student was
 * in this cohort from this date until that date, and left for this reason".
 * Promotion closes a row and opens a new one; it never rewrites the past.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_enrollments')) {
            return;
        }

        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            // Cohort placement for this period of study.
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained('programmes')->nullOnDelete();

            $table->string('academic_year', 20)->nullable();
            $table->string('term_label', 50)->nullable();          // "Semester 1", "Term 2"
            $table->unsignedSmallInteger('level_number')->nullable(); // grade or semester at the time

            $table->date('enrolled_on');
            $table->date('ended_on')->nullable();

            $table->enum('status', [
                'enrolled',    // currently studying
                'completed',   // finished this period, moving up
                'promoted',
                'repeated',
                'transferred', // left for another institution
                'withdrawn',   // left, not transferring
                'graduated',
                'suspended',
            ])->default('enrolled');

            // Why the row closed. Kept separate from status so "left because
            // the family moved" survives even though the status is 'withdrawn'.
            $table->enum('exit_reason', [
                'completed_studies', 'transferred_out', 'family_relocation',
                'financial', 'academic', 'health', 'disciplinary',
                'employment', 'other',
            ])->nullable();

            $table->string('exit_note', 500)->nullable();
            $table->string('destination_school', 150)->nullable();

            // Exactly one current enrolment per student is the invariant the
            // application maintains; this flag makes "current" cheap to query.
            $table->boolean('is_current')->default(true);

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'student_id', 'is_current'], 'enrollments_current_idx');
            $table->index(['school_id', 'class_id', 'status'], 'enrollments_class_status_idx');
            $table->index(['school_id', 'academic_year'], 'enrollments_year_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
    }
};
