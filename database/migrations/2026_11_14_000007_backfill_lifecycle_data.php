<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfills the new structures from data that already exists, so nothing is
 * lost and every current student immediately has a history.
 *
 * Uses the DB facade rather than Eloquent on purpose: model global scopes are
 * auth-dependent and would silently skip rows in a CLI migration context.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // ── 1. One "General" programme per school ────────────────────────
        foreach (DB::table('schools')->get() as $school) {
            $exists = DB::table('programmes')
                ->where('school_id', $school->id)
                ->where('code', 'GEN')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('programmes')->insert([
                'school_id'    => $school->id,
                'code'         => 'GEN',
                'name'         => 'General',
                'level_type'   => 'other',
                'description'  => 'Created automatically when the programme layer was introduced. '
                    . 'Split this into your real streams (Primary, Secondary, Diploma…) as needed.',
                'is_active'    => true,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        // ── 2. Attach every existing class to its school's programme ─────
        $schoolProgramme = DB::table('programmes')
            ->where('code', 'GEN')
            ->pluck('id', 'school_id');

        foreach (DB::table('classes')->get() as $class) {
            $updates = [];

            if (empty($class->programme_id) && isset($schoolProgramme[$class->school_id])) {
                $updates['programme_id'] = $schoolProgramme[$class->school_id];
            }

            if (empty($class->cohort_kind)) {
                $updates['cohort_kind'] = 'grade_section';
            }

            if ($class->level_number === null) {
                // "Grade 9" → 9, "KG-1" → 0, "1" → 1
                if (preg_match('/(\d+)/', (string) $class->grade, $m)) {
                    $updates['level_number'] = (int) $m[1];
                } elseif (stripos((string) $class->grade, 'kg') !== false
                    || stripos((string) $class->grade, 'nursery') !== false) {
                    $updates['level_number'] = 0;
                }
            }

            if (empty($class->shift)) {
                $updates['shift'] = 'full_day';
            }

            if (!empty($updates)) {
                $updates['updated_at'] = $now;
                DB::table('classes')->where('id', $class->id)->update($updates);
            }
        }

        // ── 3. Give every student a current enrolment ────────────────────
        foreach (DB::table('students')->get() as $student) {
            $already = DB::table('student_enrollments')
                ->where('student_id', $student->id)
                ->exists();

            if ($already) {
                continue;
            }

            $class = $student->class_id
                ? DB::table('classes')->find($student->class_id)
                : null;

            $start = $student->join_date
                ?: ($student->enrollment_date ?: substr((string) $student->created_at, 0, 10));

            // A student who has already left keeps that fact in the enrolment.
            $left = in_array($student->status, ['graduated', 'transferred', 'withdrawn'], true);

            $enrollmentId = DB::table('student_enrollments')->insertGetId([
                'school_id'     => $student->school_id,
                'student_id'    => $student->id,
                'class_id'      => $student->class_id,
                'programme_id'  => $class->programme_id ?? ($schoolProgramme[$student->school_id] ?? null),
                'academic_year' => $class->academic_year ?? null,
                'level_number'  => $class->level_number ?? null,
                'enrolled_on'   => $start,
                'ended_on'      => $left ? ($student->end_date ?: null) : null,
                'status'        => match ($student->status) {
                    'graduated'   => 'graduated',
                    'transferred' => 'transferred',
                    'withdrawn'   => 'withdrawn',
                    default       => 'enrolled',
                },
                'is_current'    => !$left,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            // ── 4. Seed the timeline with what we can prove ──────────────
            DB::table('lifecycle_events')->insert([
                'school_id'     => $student->school_id,
                'student_id'    => $student->id,
                'enrollment_id' => $enrollmentId,
                'event'         => 'enrolled',
                'occurred_on'   => $start,
                'to_label'      => $class
                    ? trim(($class->grade ?? '') . ' ' . ($class->section ?? '')
                        . ' · ' . ($class->academic_year ?? ''))
                    : null,
                'notes'         => 'Backfilled from the pre-lifecycle student record.',
                'recorded_by'   => null,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            if ($left) {
                DB::table('lifecycle_events')->insert([
                    'school_id'     => $student->school_id,
                    'student_id'    => $student->id,
                    'enrollment_id' => $enrollmentId,
                    'event'         => match ($student->status) {
                        'graduated'   => 'graduated',
                        'transferred' => 'transferred_out',
                        default       => 'withdrawn',
                    },
                    'occurred_on'   => $student->end_date ?: $start,
                    'from_label'    => $class
                        ? trim(($class->grade ?? '') . ' ' . ($class->section ?? ''))
                        : null,
                    'notes'         => 'Backfilled from the pre-lifecycle status field.',
                    'recorded_by'   => null,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }

            // ── 5. Empty profile row so the UI always has something to edit ─
            $hasProfile = DB::table('student_profiles')
                ->where('student_id', $student->id)->exists();

            if (!$hasProfile) {
                DB::table('student_profiles')->insert([
                    'school_id'  => $student->school_id,
                    'student_id' => $student->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Destructive and pointless: the data in these tables is derived from
        // `students`, which is untouched. Leave it in place if the migration is
        // rolled back so nothing a user has since added is lost.
    }
};
