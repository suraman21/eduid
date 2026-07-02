<?php

namespace App\Services;

use App\Models\ClassSubject;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\TimetableEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Academic Year Promotion (PRD §06 "Annual Renewal") — the one operation
 * that starts a new school year:
 *
 *   1. every mapped class is promoted / repeated / graduated
 *   2. the school's academic_year moves forward
 *   3. EVERY active student gets a fresh QR token for the new year —
 *      old printed cards stop scanning immediately, which is what forces
 *      the annual card re-order (the recurring revenue engine)
 *
 * Everything runs in ONE transaction under a school row lock: either the
 * whole year turns over, or nothing changes.
 */
class YearTransitionService
{
    public const ACTIONS = ['promote', 'stay', 'graduate'];

    public function __construct(private QrTokenService $qrTokens)
    {
    }

    /**
     * Best-effort "next year" label: bump every number in the string.
     * "2018 E.C." → "2019 E.C.", "2026/2027" → "2027/2028".
     */
    public static function suggestNextYear(string $year): string
    {
        return preg_replace_callback('/\d+/', fn ($m) => (string) ((int) $m[0] + 1), $year);
    }

    /**
     * Best-effort "next grade" label: bump the LAST number.
     * "Grade 9" → "Grade 10", "KG-1" → "KG-2". No number → unchanged
     * (the admin edits it in the wizard).
     */
    public static function suggestNextGrade(string $grade): string
    {
        return preg_replace_callback('/(\d+)(?!.*\d)/', fn ($m) => (string) ((int) $m[1] + 1), $grade);
    }

    /**
     * Execute the transition.
     *
     * @param array<int, array{action: string, target_grade?: ?string}> $mappings
     *        keyed by source class id; every ACTIVE class must be mapped.
     * @return array{promoted: int, stayed: int, graduated: int, tokens: int, new_classes: int}
     */
    public function run(School $school, string $newYear, array $mappings): array
    {
        $newYear = trim($newYear);

        if ($newYear === '' || $newYear === $school->academic_year) {
            throw ValidationException::withMessages([
                'new_year' => 'The new academic year must differ from the current one.',
            ]);
        }

        return DB::transaction(function () use ($school, $newYear, $mappings) {
            // Serialize: two concurrent transitions for one school would
            // double-promote. The lock also pins the row we mutate.
            $school = School::whereKey($school->id)->lockForUpdate()->firstOrFail();

            $classes = SchoolClass::withoutGlobalScope('school')
                ->where('school_id', $school->id)
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            // Every active class needs an explicit decision — silently
            // skipping one would strand its students in the old year.
            $unmapped = $classes->keys()->diff(array_keys($mappings));
            if ($unmapped->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'mappings' => 'Every active class needs an action (promote / stay / graduate).',
                ]);
            }

            $summary = ['promoted' => 0, 'stayed' => 0, 'graduated' => 0, 'tokens' => 0, 'new_classes' => 0];

            foreach ($mappings as $classId => $map) {
                $source = $classes->get((int) $classId);
                if (! $source) {
                    continue; // ids not belonging to this school's active set
                }

                $action = $map['action'] ?? '';

                if ($action === 'graduate') {
                    $summary['graduated'] += Student::withoutGlobalScope('school')
                        ->where('school_id', $school->id)
                        ->where('class_id', $source->id)
                        ->where('status', 'active')
                        ->update(['status' => 'graduated']);
                } elseif ($action === 'promote' || $action === 'stay') {
                    $grade = $action === 'promote'
                        ? trim((string) ($map['target_grade'] ?? '')) ?: self::suggestNextGrade($source->grade)
                        : $source->grade;

                    $target = SchoolClass::withoutGlobalScope('school')->firstOrCreate(
                        [
                            'school_id'     => $school->id,
                            'grade'         => $grade,
                            'section'       => $source->section,
                            'academic_year' => $newYear,
                        ],
                        [
                            'level_order' => $source->level_order + ($action === 'promote' ? 1 : 0),
                            'is_active'   => true,
                        ]
                    );
                    if ($target->wasRecentlyCreated) {
                        $summary['new_classes']++;

                        // Carry the subject list forward (teachers are NOT
                        // carried — assignments are per-year decisions the
                        // admin makes on the staff form).
                        $subjectRows = ClassSubject::withoutGlobalScope('school')
                            ->where('class_id', $source->id)
                            ->get(['subject_id', 'periods_per_week']);
                        foreach ($subjectRows as $row) {
                            ClassSubject::withoutGlobalScope('school')->firstOrCreate(
                                ['class_id' => $target->id, 'subject_id' => $row->subject_id],
                                ['school_id' => $school->id, 'periods_per_week' => $row->periods_per_week]
                            );
                        }
                    }

                    $moved = Student::withoutGlobalScope('school')
                        ->where('school_id', $school->id)
                        ->where('class_id', $source->id)
                        ->where('status', 'active')
                        ->update(['class_id' => $target->id]);

                    $summary[$action === 'promote' ? 'promoted' : 'stayed'] += $moved;
                } else {
                    throw ValidationException::withMessages([
                        'mappings' => "Unknown action '{$action}' for {$source->display_name}.",
                    ]);
                }

                // The old-year class is history either way.
                $source->update(['is_active' => false]);
            }

            // Archived classes take their timetable with them — otherwise
            // teacher dashboards and the mobile app keep showing LAST year's
            // periods forever (entries are queried by teacher, not by class
            // activity). The bell schedule (periods) survives; the new year's
            // grid is rebuilt on the Timetable page.
            TimetableEntry::withoutGlobalScope('school')
                ->where('school_id', $school->id)
                ->whereIn('class_id', $classes->keys())
                ->delete();

            // Old-year terms must stop being "current", or grade entry after
            // the transition silently defaults to a term of the previous
            // year. The admin marks a new term current when they create it.
            Term::withoutGlobalScope('school')
                ->where('school_id', $school->id)
                ->where('academic_year', '!=', $newYear)
                ->update(['is_current' => false]);

            // Turn the school's year over BEFORE minting tokens — the token
            // payload is "{uid}|{academic_year}" and must use the new year.
            $school->update(['academic_year' => $newYear]);

            // New year, new tokens: every active student's old card dies now.
            Student::withoutGlobalScope('school')
                ->where('school_id', $school->id)
                ->where('status', 'active')
                ->chunkById(200, function ($students) use ($school, &$summary) {
                    foreach ($students as $student) {
                        $this->qrTokens->generate($student, $school);
                        $summary['tokens']++;
                    }
                });

            return $summary;
        });
    }
}
