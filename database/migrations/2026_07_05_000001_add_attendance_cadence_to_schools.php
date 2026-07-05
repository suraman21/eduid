<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How often the school records student attendance each day:
 *   once      → one full-day session
 *   twice     → morning + afternoon sessions (current default behaviour)
 *   per_period→ per timetable period (needs the attendance_subject module)
 *
 * Drives which session types the web form and the mobile app offer, so the
 * school admin configures this once instead of every teacher guessing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('schools') && ! Schema::hasColumn('schools', 'attendance_cadence')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->string('attendance_cadence', 20)->default('twice');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('schools', 'attendance_cadence')) {
            Schema::table('schools', fn (Blueprint $table) => $table->dropColumn('attendance_cadence'));
        }
    }
};
