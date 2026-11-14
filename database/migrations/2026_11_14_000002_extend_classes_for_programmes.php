<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Generalises `classes` from "grade + section" into a neutral cohort.
 *
 * Nothing is renamed and no existing column is touched, so the 11 tables that
 * already reference classes.id (attendance, timetable, grades, fees, report
 * cards, teacher assignments…) keep working untouched. A class simply gains
 * the extra vocabulary a college or course centre needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            if (!Schema::hasColumn('classes', 'programme_id')) {
                $table->foreignId('programme_id')->nullable()->after('school_id')
                    ->constrained('programmes')->nullOnDelete();
            }

            // What kind of cohort this row describes. Existing rows are
            // grade sections; a college makes semester batches, a course
            // centre makes intakes.
            if (!Schema::hasColumn('classes', 'cohort_kind')) {
                $table->enum('cohort_kind', [
                    'grade_section', 'semester', 'batch', 'intake', 'group',
                ])->default('grade_section')->after('academic_year');
            }

            if (!Schema::hasColumn('classes', 'level_number')) {
                $table->unsignedSmallInteger('level_number')->nullable()->after('cohort_kind');
            }

            if (!Schema::hasColumn('classes', 'shift')) {
                $table->enum('shift', [
                    'full_day', 'morning', 'afternoon', 'evening', 'weekend',
                ])->default('full_day')->after('level_number');
            }

            if (!Schema::hasColumn('classes', 'capacity')) {
                $table->unsignedSmallInteger('capacity')->nullable()->after('shift');
            }

            if (!Schema::hasColumn('classes', 'start_date')) {
                $table->date('start_date')->nullable()->after('capacity');
            }

            if (!Schema::hasColumn('classes', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
        });

        // Relax the grade/section uniqueness so a course centre can run the
        // same cohort label twice in one year on different shifts.
        if (DB::getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE classes DROP INDEX classes_school_id_grade_section_academic_year_unique');
            } catch (\Throwable $e) {
                // Index already absent — nothing to do.
            }

            try {
                DB::statement('ALTER TABLE classes ADD UNIQUE classes_cohort_unique (school_id, programme_id, grade, section, academic_year, shift)');
            } catch (\Throwable $e) {
                // Duplicate rows already present, or index exists. The
                // application enforces uniqueness in the validator instead.
            }
        }

        Schema::table('classes', function (Blueprint $table) {
            try {
                $table->index(['school_id', 'programme_id'], 'classes_school_programme_idx');
            } catch (\Throwable $e) {
                // index already exists
            }
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            foreach (['programme_id', 'cohort_kind', 'level_number', 'shift', 'capacity', 'start_date', 'end_date'] as $col) {
                if (Schema::hasColumn('classes', $col)) {
                    if ($col === 'programme_id') {
                        try { $table->dropForeign(['programme_id']); } catch (\Throwable $e) {}
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
