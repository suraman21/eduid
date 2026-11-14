<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rest of the student file.
 *
 * `students` holds what goes on an ID card. Everything a registrar actually
 * needs — address, national ID, previous school, emergency contact, health,
 * exit details — lives here, one row per student, so the core table stays lean
 * and card printing keeps its fast path.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_profiles')) {
            return;
        }

        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_id')->unique()->constrained('students')->cascadeOnDelete();

            // ── Identity ───────────────────────────────────────────
            $table->string('national_id', 40)->nullable();
            $table->string('birth_place', 120)->nullable();
            $table->string('nationality', 60)->nullable();
            $table->string('mother_tongue', 60)->nullable();
            $table->string('religion', 60)->nullable();

            // ── Address & own contact ──────────────────────────────
            $table->string('region', 80)->nullable();
            $table->string('zone', 80)->nullable();
            $table->string('woreda', 80)->nullable();
            $table->string('kebele', 80)->nullable();
            $table->string('house_number', 40)->nullable();
            $table->string('address_line', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 120)->nullable();

            // ── Emergency contact ──────────────────────────────────
            $table->string('emergency_contact_name', 120)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->string('emergency_contact_relation', 40)->nullable();

            // ── Prior education / admission ────────────────────────
            $table->string('previous_school', 150)->nullable();
            $table->string('previous_school_year', 20)->nullable();
            $table->string('previous_grade', 40)->nullable();
            $table->string('admission_source', 80)->nullable();  // referral, walk-in, radio…

            // ── Health ─────────────────────────────────────────────
            $table->string('allergies', 255)->nullable();
            $table->string('chronic_conditions', 255)->nullable();
            $table->string('insurance_provider', 120)->nullable();
            $table->string('insurance_number', 60)->nullable();
            $table->text('special_needs')->nullable();

            // ── Exit record ────────────────────────────────────────
            $table->string('exit_reason_category', 60)->nullable();
            $table->text('exit_note')->nullable();
            $table->string('exit_destination', 150)->nullable();

            $table->timestamps();

            $table->index(['school_id'], 'profiles_school_idx');
            $table->index(['school_id', 'national_id'], 'profiles_national_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
