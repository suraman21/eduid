<?php

namespace App\Services;

use App\Models\AssessmentConfig;
use App\Models\GradingScale;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Support\Collection;

/**
 * Builds a student's academic record across every year they have been here.
 *
 * This was impossible before the lifecycle work: results hang off terms, terms
 * hang off academic years, and nothing tied a year to the class the student was
 * actually in. Enrolments supply that link, so the transcript can now say
 * "Grade 5 A, 2026/2027, average 78.4, GPA 2.6" and back it with real marks.
 *
 * Only `published` grades are used — anything a teacher has not released never
 * appears on a document the school signs.
 */
class TranscriptService
{
    private const DEFAULT_WEIGHTS = ['ca' => 0.40, 'midterm' => 0.20, 'final' => 0.40];

    public function __construct(private EnrollmentService $enrollments)
    {
    }

    /**
     * Assemble the full academic record.
     *
     * @return array<string,mixed>
     */
    public function build(Student $student): array
    {
        $schoolId = $student->school_id;

        $grades = StudentGrade::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('student_id', $student->id)
            ->where('status', 'published')
            ->with(['subject:id,name,code', 'term:id,name,academic_year,order,weight'])
            ->get();

        $enrollments = StudentEnrollment::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('student_id', $student->id)
            ->with('cohort:id,grade,section,academic_year')
            ->orderBy('enrolled_on')
            ->get();

        $scales = GradingScale::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->orderByDesc('min_score')
            ->get();

        // Group marks: academic year → term → subject → assessment type
        $byYear = $grades->groupBy(fn ($g) => $g->term?->academic_year ?? 'Unknown');

        $years = [];

        // Every year the student was actually enrolled, plus any year that has
        // marks even if the enrolment record is missing.
        $yearKeys = collect($enrollments->pluck('academic_year'))
            ->merge($byYear->keys())
            ->filter()
            ->unique()
            ->sort()
            ->values();

        foreach ($yearKeys as $year) {
            // Only the enrolment that actually belongs to this year may label it.
            // Falling back to the student's current class would print today's
            // grade next to an older year on an official document.
            $enrollment = $enrollments->firstWhere('academic_year', $year);

            $yearGrades = $byYear[$year] ?? collect();

            if ($yearGrades->isEmpty()) {
                // Enrolled but nothing marked yet — still a real year of study.
                $years[] = [
                    'academic_year' => $year,
                    'level_label'   => $this->levelLabel($enrollment),
                    'status'        => $enrollment?->status,
                    'enrolled_on'   => $enrollment?->enrolled_on?->format('Y-m-d'),
                    'ended_on'      => $enrollment?->ended_on?->format('Y-m-d'),
                    'terms'         => [],
                    'average'       => null,
                    'letter'        => null,
                    'gpa'           => null,
                    'has_results'   => false,
                ];
                continue;
            }

            $config = $this->weightsFor($schoolId, $year, $enrollment);

            $terms = $yearGrades
                ->groupBy('term_id')
                ->map(function (Collection $termGrades, $termId) use ($config, $scales) {
                    $term = $termGrades->first()->term;

                    $rows = $this->subjectRows($termGrades, $config, $scales);
                    $totals = array_values(array_filter(array_column($rows, 'total'), fn ($t) => $t !== null));
                    $average = $totals ? round(array_sum($totals) / count($totals), 1) : null;

                    return [
                        'term_id' => (int) $termId,
                        'name'    => $term?->name ?? 'Term',
                        'order'   => (int) ($term?->order ?? 0),
                        'weight'  => $term?->weight !== null ? (float) $term->weight : null,
                        'subjects'=> $rows,
                        'average' => $average,
                        'letter'  => $this->letterFor($average, $scales),
                        'gpa'     => $this->gpaFor($rows),
                    ];
                })
                ->sortBy('order')
                ->values()
                ->all();

            $allTotals = [];
            $allPoints = [];
            foreach ($terms as $t) {
                foreach ($t['subjects'] as $s) {
                    if ($s['total'] !== null) {
                        $allTotals[] = $s['total'];
                    }
                    if ($s['gpa_point'] !== null) {
                        $allPoints[] = $s['gpa_point'];
                    }
                }
            }

            $yearAverage = $allTotals ? round(array_sum($allTotals) / count($allTotals), 1) : null;

            $years[] = [
                'academic_year' => $year,
                'level_label'   => $this->levelLabel($enrollment),
                'status'        => $enrollment?->status,
                'enrolled_on'   => $enrollment?->enrolled_on?->format('Y-m-d'),
                'ended_on'      => $enrollment?->ended_on?->format('Y-m-d'),
                'terms'         => $terms,
                'average'       => $yearAverage,
                'letter'        => $this->letterFor($yearAverage, $scales),
                'gpa'           => $allPoints ? round(array_sum($allPoints) / count($allPoints), 2) : null,
                'has_results'   => true,
            ];
        }

        // ── Cumulative ──────────────────────────────────────────────
        $cumTotals = [];
        $cumPoints = [];
        $subjectsSeen = [];

        foreach ($years as $y) {
            foreach ($y['terms'] as $t) {
                foreach ($t['subjects'] as $s) {
                    if ($s['total'] !== null) {
                        $cumTotals[] = $s['total'];
                        $subjectsSeen[$s['subject_id']] = true;
                    }
                    if ($s['gpa_point'] !== null) {
                        $cumPoints[] = $s['gpa_point'];
                    }
                }
            }
        }

        $cumAverage = $cumTotals ? round(array_sum($cumTotals) / count($cumTotals), 1) : null;

        return [
            'student' => [
                'id'         => $student->id,
                'uid'        => $student->uid,
                'full_name'  => $student->full_name,
                'full_name_am' => $student->full_name_am,
                'gender'     => $student->gender,
                'date_of_birth' => $student->date_of_birth?->format('Y-m-d'),
                'photo_url'  => $student->photo_url,
            ],
            'years' => $years,
            'cumulative' => [
                'average'         => $cumAverage,
                'letter'          => $this->letterFor($cumAverage, $scales),
                'gpa'             => $cumPoints ? round(array_sum($cumPoints) / count($cumPoints), 2) : null,
                'years_count'     => count(array_filter($years, fn ($y) => $y['has_results'])),
                'subjects_count'  => count($subjectsSeen),
                'marks_count'     => count($cumTotals),
            ],
            'scale' => $scales->map(fn ($s) => [
                'label'     => $s->label,
                'min_score' => (float) $s->min_score,
                'max_score' => (float) $s->max_score,
                'gpa_point' => (float) $s->gpa_point,
            ])->all(),
            'has_marks'    => !$grades->isEmpty(),
            'generated_at' => now()->format('Y-m-d H:i'),
        ];
    }

    /**
     * Turn one term's marks into per-subject rows.
     *
     * @return array<int,array<string,mixed>>
     */
    private function subjectRows(Collection $termGrades, array $weights, Collection $scales): array
    {
        $rows = [];

        foreach ($termGrades->groupBy('subject_id') as $subjectId => $marks) {
            $byType = $marks->keyBy('assessment_type');
            $subject = $marks->first()->subject;

            $normalised = [];
            foreach (['ca', 'midterm', 'final'] as $type) {
                $mark = $byType[$type] ?? null;
                if ($mark && (float) $mark->max_score > 0) {
                    $normalised[$type] = round((float) $mark->score / (float) $mark->max_score * 100, 1);
                } else {
                    $normalised[$type] = null;
                }
            }

            // A total needs every component; a partial term is shown as "—"
            // rather than being reported as if it were complete.
            $complete = !in_array(null, $normalised, true);

            $total = $complete
                ? round(
                    $normalised['ca'] * $weights['ca']
                    + $normalised['midterm'] * $weights['midterm']
                    + $normalised['final'] * $weights['final'],
                    1,
                )
                : null;

            $rows[] = [
                'subject_id' => (int) $subjectId,
                'subject'    => $subject?->name ?? 'Subject',
                'code'       => $subject?->code,
                'ca'         => $normalised['ca'],
                'midterm'    => $normalised['midterm'],
                'final'      => $normalised['final'],
                'total'      => $total,
                'letter'     => $this->letterFor($total, $scales),
                'gpa_point'  => $this->pointFor($total, $scales),
                'complete'   => $complete,
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['subject'], $b['subject']));

        return $rows;
    }

    private function weightsFor(int $schoolId, string $year, ?StudentEnrollment $enrollment): array
    {
        $query = AssessmentConfig::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('academic_year', $year);

        // Prefer a config matching the student's level, else the school-wide one.
        $config = null;

        if ($enrollment?->cohort?->grade) {
            $config = (clone $query)->where('grade_level', $enrollment->cohort->grade)->first();
        }

        $config ??= (clone $query)->whereNull('grade_level')->first();

        if (!$config) {
            return self::DEFAULT_WEIGHTS;
        }

        $sum = (float) $config->ca_weight + (float) $config->midterm_weight + (float) $config->final_weight;

        if ($sum <= 0) {
            return self::DEFAULT_WEIGHTS;
        }

        return [
            'ca'      => (float) $config->ca_weight / 100,
            'midterm' => (float) $config->midterm_weight / 100,
            'final'   => (float) $config->final_weight / 100,
        ];
    }

    private function letterFor(?float $score, Collection $scales): ?string
    {
        if ($score === null || $scales->isEmpty()) {
            return null;
        }

        foreach ($scales as $scale) {
            if ($scale->matches($score)) {
                return $scale->label;
            }
        }

        return null;
    }

    private function pointFor(?float $score, Collection $scales): ?float
    {
        if ($score === null || $scales->isEmpty()) {
            return null;
        }

        foreach ($scales as $scale) {
            if ($scale->matches($score)) {
                return (float) $scale->gpa_point;
            }
        }

        return null;
    }

    private function gpaFor(array $rows): ?float
    {
        $points = array_values(array_filter(array_column($rows, 'gpa_point'), fn ($p) => $p !== null));

        return $points ? round(array_sum($points) / count($points), 2) : null;
    }

    private function levelLabel(?StudentEnrollment $enrollment): ?string
    {
        if (!$enrollment) {
            return null;
        }

        if ($enrollment->cohort) {
            return trim($enrollment->cohort->grade . ' ' . $enrollment->cohort->section);
        }

        return $enrollment->academic_year;
    }
}
