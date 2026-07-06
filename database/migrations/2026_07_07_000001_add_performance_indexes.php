<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for the hot query paths that make the school panel feel
 * slow, especially the dashboard and roster/attendance counts.
 *
 * Background: `students.status` (the enum the app filters on — "active" seat
 * counting) was added AFTER the table was created and never got an index (only
 * the legacy `is_active` did). Every "active students" count therefore scanned.
 * Same story for the "sessions today" and per-class "taken today" counts.
 *
 * Each index is added defensively so the migration is safe on MySQL and the
 * SQLite test database, and a no-op if an index somehow already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('students', ['school_id', 'status'], 'students_school_status_idx');
        $this->addIndex('students', ['class_id', 'status'], 'students_class_status_idx');
        $this->addIndex('attendance_sessions', ['school_id', 'session_date'], 'att_sessions_school_date_idx');
        $this->addIndex('attendance_sessions', ['class_id', 'session_date'], 'att_sessions_class_date_idx');
        $this->addIndex('timetable_entries', ['teacher_id', 'day_of_week'], 'tt_entries_teacher_day_idx');
    }

    public function down(): void
    {
        $this->dropIndex('students', 'students_school_status_idx');
        $this->dropIndex('students', 'students_class_status_idx');
        $this->dropIndex('attendance_sessions', 'att_sessions_school_date_idx');
        $this->dropIndex('attendance_sessions', 'att_sessions_class_date_idx');
        $this->dropIndex('timetable_entries', 'tt_entries_teacher_day_idx');
    }

    private function addIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        try {
            Schema::table($table, function (Blueprint $t) use ($columns, $name) {
                $t->index($columns, $name);
            });
        } catch (\Throwable $e) {
            // Index already present (re-run / pre-existing) — ignore.
        }
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        try {
            Schema::table($table, function (Blueprint $t) use ($name) {
                $t->dropIndex($name);
            });
        } catch (\Throwable $e) {
            // Not present — ignore.
        }
    }
};
