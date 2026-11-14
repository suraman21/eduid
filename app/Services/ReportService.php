<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\GradingScale;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGrade;
use Illuminate\Support\Facades\DB;

/**
 * Read-only reporting over the life of the school.
 *
 * Everything here is computed from the records the other modules already
 * keep — enrolments, lifecycle exits, published marks and attendance — so a
 * report can never disagree with the screens it summarises. No new tables.
 *
 * Two deliberate stances:
 *  - Absence of data is shown as absence, never as zero. A subject with no
 *    published marks reads "not marked yet", not "0% average", because the
 *    difference matters to whoever is reading the report.
 *  - Rates are only computed where the denominator is meaningful. A retention
 *    figure off a base of two students is noise dressed as truth.
 */
class ReportService
{
    /** Below this a rate is printed but flagged as too small to lean on. */
    public const SMALL_BASE = 10;

    /** A student is flagged at risk at or below this attendance rate. */
    public const ATTENDANCE_RISK = 80.0;

    /** ...or with an average below this. */
    public const ACADEMIC_RISK = 50.0;

    // ── Enrolment ───────────────────────────────────────────────

    /**
     * Headcount by academic year, with intake and exits per year.
     */
    public function enrolment(int $schoolId): array
    {
        // Distinct students per year — a student who moved class mid-year is
        // one child, not two.
        $byYear = StudentEnrollment::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->selectRaw('academic_year, COUNT(DISTINCT student_id) as total')
            ->groupBy('academic_year')
            ->orderBy('academic_year')
            ->pluck('total', 'academic_year');

        // "Entries" means genuinely new to the school: their first-ever
        // enrolment falls in this year. Counting rows in the year would just
        // repeat the headcount under a different heading.
        $firstYear = StudentEnrollment::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->get(['id', 'student_id', 'academic_year', 'enrolled_on'])
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->sortBy('enrolled_on')->first()->academic_year);

        $intake = $firstYear->countBy();

        // Exits: students whose LAST enrolment is this year and ended badly.
        $lastYear = StudentEnrollment::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->get(['id', 'student_id', 'academic_year', 'enrolled_on', 'status'])
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->sortBy('enrolled_on')->last());

        $exits = $lastYear
            ->filter(fn ($e) => in_array($e->status, ['transferred', 'withdrawn', 'graduated', 'completed', 'suspended'], true))
            ->countBy(fn ($e) => $e->academic_year);

        // Legacy rows can carry a null academic_year. Showing a blank cell in
        // a report looks like a rendering bug, so it is labelled — and sorted
        // last — so the data problem is visible rather than hidden.
        $byYear = $byYear
            ->mapWithKeys(fn ($v, $k) => [(string) $k => $v])
            ->sortKeys();

        if ($byYear->has('')) {
            $blank = $byYear->pull('');
            $byYear->put('Unspecified', $blank);
        }

        $years = [];
        $previous = null;

        foreach ($byYear as $year => $total) {
            $total = (int) $total;

            $years[] = [
                'academic_year' => $year === 'Unspecified' ? 'Unspecified' : $year,
                'students'      => $total,
                'entries'       => (int) ($intake[$year] ?? 0),
                'exits'         => (int) ($exits[$year] ?? 0),
                'change'        => $previous === null || $year === 'Unspecified' ? null : $total - $previous,
                'change_pct'    => $previous === null || $previous === 0 || $year === 'Unspecified'
                    ? null
                    : round(($total - $previous) / $previous * 100, 1),
            ];

            $previous = $total;
        }

        // Gender split across everyone currently enrolled.
        $currentIds = StudentEnrollment::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('is_current', true)
            ->pluck('student_id');

        $gender = Student::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->whereIn('id', $currentIds)
            ->selectRaw("COALESCE(gender,'unspecified') as g, COUNT(*) as total")
            ->groupBy('g')
            ->pluck('total', 'g');

        // Headcount per class, current enrolments only.
        $byClass = StudentEnrollment::withoutGlobalScopes()
            ->where('student_enrollments.school_id', $schoolId)
            ->where('student_enrollments.is_current', true)
            ->join('classes', 'classes.id', '=', 'student_enrollments.class_id')
            ->selectRaw('student_enrollments.class_id, classes.grade, classes.section, classes.academic_year, classes.capacity, COUNT(*) as total')
            ->groupBy('student_enrollments.class_id', 'classes.grade', 'classes.section', 'classes.academic_year', 'classes.capacity')
            ->orderBy('classes.grade')
            ->orderBy('classes.section')
            ->get()
            ->map(fn ($r) => [
                'class_id'      => (int) $r->class_id,
                'label'         => trim(($r->grade ?? '') . ' ' . ($r->section ?? '')),
                'academic_year' => $r->academic_year,
                'students'      => (int) $r->total,
                'capacity'      => $r->capacity !== null ? (int) $r->capacity : null,
                'fill_pct'      => $r->capacity
                    ? round((int) $r->total / (int) $r->capacity * 100, 1)
                    : null,
            ])
            ->all();

        $totalCurrent = StudentEnrollment::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('is_current', true)
            ->count();

        return [
            'years'   => $years,
            'gender'  => [
                'male'        => (int) ($gender['male'] ?? 0),
                'female'      => (int) ($gender['female'] ?? 0),
                'unspecified' => (int) ($gender['unspecified'] ?? 0),
            ],
            'by_class'      => $byClass,
            'current_total' => (int) $totalCurrent,
            'small_base'    => self::SMALL_BASE,
        ];
    }

    // ── Retention ───────────────────────────────────────────────

    /**
     * Cohort retention: take everyone who first appears in year X, then ask
     * where they are now. This is the question a head teacher actually asks —
     * "of the children who joined us in 2024, how many are still here?"
     */
    public function retention(int $schoolId): array
    {
        $all = StudentEnrollment::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->orderBy('enrolled_on')
            ->get(['id', 'student_id', 'academic_year', 'status', 'is_current', 'enrolled_on']);

        $cohorts = [];

        foreach ($all->groupBy('student_id') as $studentId => $rows) {
            $first = $rows->sortBy('enrolled_on')->first();
            $cohorts[$first->academic_year][] = $rows;
        }

        ksort($cohorts);

        $rows = [];

        foreach ($cohorts as $year => $students) {
            $year = ($year === null || $year === '') ? 'Unspecified' : $year;
            $joined = count($students);

            $stillHere = 0;
            $graduated = 0;
            $transferred = 0;
            $withdrawn = 0;
            $other = 0;

            foreach ($students as $enrollments) {
                $current = $enrollments->firstWhere('is_current', true);
                if ($current) {
                    $stillHere++;
                    continue;
                }

                $last = $enrollments->sortByDesc('enrolled_on')->first();
                match ($last->status) {
                    'graduated', 'completed'   => $graduated++,
                    'transferred'              => $transferred++,
                    'withdrawn'                => $withdrawn++,
                    default                    => $other++,
                };
            }

            // Retention = of the cohort, how many stayed rather than left early.
            // Graduates did not fail to be retained — they finished.
            $leavers = $transferred + $withdrawn + $other;

            $rows[] = [
                'academic_year' => $year,
                'joined'        => $joined,
                'still_enrolled'=> $stillHere,
                'graduated'     => $graduated,
                'transferred'   => $transferred,
                'withdrawn'     => $withdrawn,
                'other'         => $other,
                'leavers'       => $leavers,
                'retention_pct' => $joined > 0
                    ? round(($joined - $leavers) / $joined * 100, 1)
                    : null,
                'small_base'    => $joined < self::SMALL_BASE,
            ];
        }

        // Why people leave, across all time.
        $reasons = StudentEnrollment::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->whereNotNull('exit_reason')
            ->selectRaw('exit_reason, COUNT(*) as total')
            ->groupBy('exit_reason')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'reason' => str_replace('_', ' ', (string) $r->exit_reason),
                'total'  => (int) $r->total,
            ])
            ->all();

        return ['cohorts' => $rows, 'exit_reasons' => $reasons];
    }

    // ── Academic results ────────────────────────────────────────

    /**
     * Results by class and subject from published marks only.
     *
     * Uses the same 40/20/40 weighting as the report card and the transcript,
     * so the three can never disagree about a subject.
     */
    public function academic(int $schoolId, ?string $academicYear = null): array
    {
        $grades = StudentGrade::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('status', 'published')
            ->with([
                'subject:id,name,code',
                'term:id,name,academic_year',
            ])
            ->when($academicYear, fn ($q) => $q->whereHas('term', fn ($t) => $t->where('academic_year', $academicYear)))
            ->get();

        $scales = GradingScale::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->orderByDesc('min_score')
            ->get();

        // student+subject+term → the three component scores
        $cells = [];

        foreach ($grades as $g) {
            $key = ($g->student_id ?? 0) . '|' . $g->subject_id . '|' . $g->term_id;

            $cells[$key] ??= [
                'class_id'      => $g->class_id,
                'subject_id'    => $g->subject_id,
                'subject'       => $g->subject?->name,
                'academic_year' => $g->term?->academic_year,
                'parts'         => [],
            ];

            $pct = $g->max_score > 0 ? (float) $g->score / (float) $g->max_score * 100 : null;
            $cells[$key]['parts'][$g->assessment_type] = $pct;
        }

        $bySubject = [];

        foreach ($cells as $cell) {
            $p = $cell['parts'];
            if (! isset($p['ca'], $p['midterm'], $p['final'])) {
                continue;   // incomplete — same rule as the report card
            }

            $total = round($p['ca'] * 0.40 + $p['midterm'] * 0.20 + $p['final'] * 0.40, 1);

            $bySubject[$cell['subject_id']] ??= [
                'subject_id'    => $cell['subject_id'],
                'subject'       => $cell['subject'],
                'academic_year' => $cell['academic_year'],
                'scores'        => [],
            ];

            $bySubject[$cell['subject_id']]['scores'][] = $total;
        }

        $classLabels = SchoolClass::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->get()
            ->mapWithKeys(fn ($c) => [$c->id => trim(($c->grade ?? '') . ' ' . ($c->section ?? ''))]);

        $subjects = collect($bySubject)->map(function ($s) use ($scales) {
            $scores = $s['scores'];
            $n = count($scores);

            $avg = $n > 0 ? round(array_sum($scores) / $n, 1) : null;

            $bands = [];
            foreach ($scales as $scale) {
                $bands[$scale->label] = count(array_filter(
                    $scores,
                    fn ($v) => $v >= (float) $scale->min_score && $v <= (float) $scale->max_score
                ));
            }

            $passMark = $this->passMark($scales);

            return [
                'subject_id'    => $s['subject_id'],
                'subject'       => $s['subject'],
                'academic_year' => $s['academic_year'],
                'marked'        => $n,
                'average'       => $avg,
                'highest'       => $n ? max($scores) : null,
                'lowest'        => $n ? min($scores) : null,
                'pass_rate'     => $n
                    ? round(count(array_filter($scores, fn ($v) => $v >= $passMark)) / $n * 100, 1)
                    : null,
                'bands'         => $bands,
            ];
        })->sortBy('subject')->values()->all();

        // Grade distribution for all published results combined.
        $allScores = [];
        foreach ($bySubject as $s) {
            foreach ($s['scores'] as $v) {
                $allScores[] = $v;
            }
        }

        $distribution = [];
        foreach ($scales as $scale) {
            $distribution[] = [
                'label' => $scale->label,
                'range' => (float) $scale->min_score . '–' . (float) $scale->max_score,
                'count' => count(array_filter(
                    $allScores,
                    fn ($v) => $v >= (float) $scale->min_score && $v <= (float) $scale->max_score
                )),
            ];
        }

        $years = StudentGrade::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('status', 'published')
            ->whereHas('term')
            ->with('term:id,academic_year')
            ->get()
            ->pluck('term.academic_year')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'subjects'     => $subjects,
            'distribution' => $distribution,
            'class_labels' => $classLabels,
            'years'        => $years,
            'academic_year'=> $academicYear,
            'pass_mark'    => $this->passMark($scales),
            'total_marked' => count($allScores),
            'overall_average' => count($allScores)
                ? round(array_sum($allScores) / count($allScores), 1)
                : null,
        ];
    }

    // ── At risk ─────────────────────────────────────────────────

    /**
     * Students who need a conversation this week.
     *
     * Two independent signals: attendance and marks. Either alone can be a bad
     * week; both together is a pattern. The flags are listed separately so the
     * reader can see which signal fired, and both are reported as "unknown"
     * rather than "fine" when there is no data at all.
     */
    public function atRisk(int $schoolId): array
    {
        $currentEnrolments = StudentEnrollment::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('is_current', true)
            ->with('cohort:id,grade,section')
            ->get();

        $currentIds = $currentEnrolments->pluck('student_id')->unique()->values();

        // The class a student is in is the one on their open enrolment, not
        // the legacy students.class_id column — the two can drift apart, and a
        // report that disagrees with the enrolment record is worse than none.
        $classOf = $currentEnrolments
            ->keyBy('student_id')
            ->map(fn ($e) => $e->cohort ? trim($e->cohort->grade . ' ' . $e->cohort->section) : null);

        if ($currentIds->isEmpty()) {
            return ['students' => [], 'attendance_risk' => 0, 'academic_risk' => 0, 'both' => 0, 'checked' => 0];
        }

        // Attendance rate per student: present + late + excused all count as
        // attended in the sense that matters here — absence is the signal.
        $attendance = AttendanceRecord::query()
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.session_id')
            ->where('attendance_sessions.school_id', $schoolId)
            ->whereIn('attendance_records.student_id', $currentIds)
            ->selectRaw("attendance_records.student_id,
                COUNT(*) as total,
                SUM(CASE WHEN attendance_records.status = 'absent' THEN 1 ELSE 0 END) as absences")
            ->groupBy('attendance_records.student_id')
            ->get()
            ->mapWithKeys(fn ($r) => [
                $r->student_id => [
                    'total'    => (int) $r->total,
                    'absences' => (int) $r->absences,
                    'rate'     => (int) $r->total > 0
                        ? round(((int) $r->total - (int) $r->absences) / (int) $r->total * 100, 1)
                        : null,
                ],
            ]);

        $academics = $this->subjectTotalsFor($schoolId, $currentIds->all());

        $students = Student::withoutGlobalScopes()
            ->whereIn('id', $currentIds)
            ->get();

        $rows = [];

        foreach ($students as $s) {
            $att = $attendance[$s->id] ?? null;
            $acad = $academics[$s->id] ?? null;

            $reasons = [];

            $attRate = $att['rate'] ?? null;
            if ($attRate !== null && $attRate <= self::ATTENDANCE_RISK) {
                $reasons[] = "Attendance {$attRate}%";
            }

            $avg = $acad['average'] ?? null;
            $failing = $acad['failing'] ?? 0;
            if ($avg !== null && $avg < self::ACADEMIC_RISK) {
                $reasons[] = "Average {$avg}%";
            } elseif ($failing > 0) {
                $reasons[] = $failing === 1 ? 'Failing 1 subject' : "Failing {$failing} subjects";
            }

            if (empty($reasons)) {
                continue;
            }

            $rows[] = [
                'id'             => $s->id,
                'name'           => $s->full_name,
                'uid'            => $s->uid,
                'class'          => $classOf[$s->id] ?? null,
                'attendance'     => $attRate,
                'attendance_base'=> $att['total'] ?? 0,
                'absences'       => $att['absences'] ?? 0,
                'average'        => $avg,
                'failing'        => $failing,
                'subjects'       => $acad['subjects'] ?? [],
                'reasons'        => $reasons,
                'severity'       => count($reasons) > 1 ? 'high' : 'medium',
            ];
        }

        usort($rows, fn ($a, $b) => ($b['severity'] === 'high') <=> ($a['severity'] === 'high'));

        return [
            'students'        => $rows,
            'attendance_risk' => count(array_filter($rows, fn ($r) => $r['attendance'] !== null && $r['attendance'] <= self::ATTENDANCE_RISK)),
            'academic_risk'   => count(array_filter($rows, fn ($r) => $r['average'] !== null && $r['average'] < self::ACADEMIC_RISK)),
            'both'            => count(array_filter($rows, fn ($r) => $r['severity'] === 'high')),
            'checked'         => $currentIds->count(),
        ];
    }

    /** Per-student totals across subjects, using the report-card weighting. */
    private function subjectTotalsFor(int $schoolId, array $studentIds): array
    {
        $grades = StudentGrade::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('status', 'published')
            ->whereIn('student_id', $studentIds)
            ->with('subject:id,name')
            ->get();

        $cells = [];

        foreach ($grades as $g) {
            $key = $g->student_id . '|' . $g->subject_id . '|' . $g->term_id;
            $cells[$key] ??= [
                'student_id' => $g->student_id,
                'subject'    => $g->subject?->name,
                'parts'      => [],
            ];
            $cells[$key]['parts'][$g->assessment_type] = $g->max_score > 0
                ? (float) $g->score / (float) $g->max_score * 100
                : null;
        }

        $scales = GradingScale::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->orderByDesc('min_score')
            ->get();

        $passMark = $this->passMark($scales);
        $out = [];

        foreach ($cells as $cell) {
            $p = $cell['parts'];
            if (! isset($p['ca'], $p['midterm'], $p['final'])) {
                continue;
            }

            $total = round($p['ca'] * 0.40 + $p['midterm'] * 0.20 + $p['final'] * 0.40, 1);

            $out[$cell['student_id']] ??= ['scores' => [], 'subjects' => []];
            $out[$cell['student_id']]['scores'][] = $total;
            $out[$cell['student_id']]['subjects'][] = [
                'subject' => $cell['subject'],
                'total'   => $total,
                'failed'  => $total < $passMark,
            ];
        }

        return collect($out)->map(function ($row) use ($passMark) {
            $scores = $row['scores'];

            return [
                'average'  => count($scores) ? round(array_sum($scores) / count($scores), 1) : null,
                'failing'  => count(array_filter($scores, fn ($v) => $v < $passMark)),
                'subjects' => array_values(array_filter($row['subjects'], fn ($s) => $s['failed'])),
            ];
        })->all();
    }

    /**
     * The lowest passing mark, taken from the school's own grading scale
     * rather than hard-coded — the first band whose GPA point is above zero.
     */
    private function passMark($scales): float
    {
        $passing = $scales
            ->filter(fn ($s) => (float) $s->gpa_point > 0)
            ->sortBy('min_score')
            ->first();

        return $passing ? (float) $passing->min_score : 50.0;
    }
}
