<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AssessmentConfig;
use App\Models\ClassSubject;
use App\Models\GradingScale;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Term;

class GradeController extends BaseApiController
{
    public function grades(SchoolClass $class)
    {
        if (! $this->canAccessClass($class->id)) {
            return response()->json(['message' => 'You are not assigned to this class.'], 403);
        }

        $school = $this->currentSchool();

        $termId = request()->query('term_id');
        $term = $termId
            ? Term::where('id', $termId)->firstOrFail()
            : Term::where('is_current', true)->first();

        // No term configured yet is a normal not-set-up state, not an error.
        // Return 200 with a clear notice so the app can explain the next step
        // instead of showing a misleading "no grades / offline" message.
        if (! $term) {
            return response()->json([
                'class'    => ['id' => $class->id, 'name' => $class->display_name],
                'term'     => null,
                'weights'  => ['ca' => 40, 'midterm' => 20, 'final' => 40],
                'students' => [],
                'notice'   => 'no_term',
                'notice_message' => 'No academic term has been set up yet. Ask your school admin to create a term (Grades → Terms) before grades can be recorded.',
            ]);
        }

        // Subjects must be assigned to the class before grades mean anything.
        if (ClassSubject::where('class_id', $class->id)->doesntExist()) {
            return response()->json([
                'class'    => ['id' => $class->id, 'name' => $class->display_name],
                'term'     => ['id' => $term->id, 'name' => $term->name, 'academic_year' => $term->academic_year],
                'weights'  => ['ca' => 40, 'midterm' => 20, 'final' => 40],
                'students' => [],
                'notice'   => 'no_subjects',
                'notice_message' => 'No subjects are assigned to this class yet. Assign subjects on the web (Classes → Subjects) so grades can be entered.',
            ]);
        }

        $config = AssessmentConfig::where('school_id', $school->id)
            ->where('academic_year', $term->academic_year)
            ->whereNull('grade_level')
            ->first();

        $caW  = $config ? (float) $config->ca_weight / 100 : 0.40;
        $midW = $config ? (float) $config->midterm_weight / 100 : 0.20;
        $finW = $config ? (float) $config->final_weight / 100 : 0.40;

        $students = Student::where('class_id', $class->id)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();

        $subjects = ClassSubject::where('class_id', $class->id)
            ->with('subject')
            ->get();

        $allGrades = StudentGrade::where('class_id', $class->id)
            ->where('term_id', $term->id)
            ->get()
            ->groupBy('student_id');

        $scales = GradingScale::where('school_id', $school->id)
            ->orderByDesc('min_score')
            ->get();

        $rows = [];
        foreach ($students as $student) {
            $sg = $allGrades[$student->id] ?? collect();
            $subjectResults = [];

            foreach ($subjects as $cs) {
                $byType = $sg->where('subject_id', $cs->subject_id)->keyBy('assessment_type');
                $ca  = $byType['ca'] ?? null;
                $mid = $byType['midterm'] ?? null;
                $fin = $byType['final'] ?? null;

                $total = null;
                $letter = null;
                if ($ca && $mid && $fin) {
                    $caS  = (float) $ca->score / (float) $ca->max_score * 100;
                    $midS = (float) $mid->score / (float) $mid->max_score * 100;
                    $finS = (float) $fin->score / (float) $fin->max_score * 100;
                    $total = round($caS * $caW + $midS * $midW + $finS * $finW, 1);
                    $letter = $scales->first(fn ($s) => $s->matches($total))?->label;
                }

                $subjectResults[] = [
                    'subject_id'   => $cs->subject_id,
                    'subject_name' => $cs->subject->name,
                    'ca'           => $ca ? ['score' => (float) $ca->score, 'max' => (float) $ca->max_score] : null,
                    'midterm'      => $mid ? ['score' => (float) $mid->score, 'max' => (float) $mid->max_score] : null,
                    'final'        => $fin ? ['score' => (float) $fin->score, 'max' => (float) $fin->max_score] : null,
                    'total'        => $total,
                    'letter'       => $letter,
                ];
            }

            $validTotals = array_filter(array_column($subjectResults, 'total'), fn ($t) => $t !== null);
            $termAvg = count($validTotals) > 0 ? round(array_sum($validTotals) / count($validTotals), 1) : null;

            $rows[] = [
                'student_id'  => $student->id,
                'student_uid' => $student->uid,
                'full_name'   => $student->full_name,
                'subjects'    => $subjectResults,
                'term_avg'    => $termAvg,
                'rank'        => null,
            ];
        }

        usort($rows, function ($a, $b) {
            if ($a['term_avg'] === null && $b['term_avg'] === null) return 0;
            if ($a['term_avg'] === null) return 1;
            if ($b['term_avg'] === null) return -1;
            return $b['term_avg'] <=> $a['term_avg'];
        });

        $rank = 0;
        $prevAvg = null;
        $pos = 0;
        foreach ($rows as &$row) {
            if ($row['term_avg'] === null) continue;
            $pos++;
            if ($row['term_avg'] !== $prevAvg) $rank = $pos;
            $row['rank'] = $rank;
            $prevAvg = $row['term_avg'];
        }
        unset($row);

        // Distinguish "class has no students" from "no grades entered yet" so
        // the app shows the right message rather than a bare empty list.
        $anyGrade = $allGrades->flatten()->isNotEmpty();
        $notice = null;
        $noticeMessage = null;
        if ($students->isEmpty()) {
            $notice = 'no_students';
            $noticeMessage = 'This class has no active students yet.';
        } elseif (! $anyGrade) {
            $notice = 'no_grades';
            $noticeMessage = 'No grades have been entered for this term yet. Grades appear here once teachers record and the admin publishes them.';
        }

        return response()->json([
            'class' => ['id' => $class->id, 'name' => $class->display_name],
            'term'  => ['id' => $term->id, 'name' => $term->name, 'academic_year' => $term->academic_year],
            'weights' => [
                'ca' => $config ? (float) $config->ca_weight : 40,
                'midterm' => $config ? (float) $config->midterm_weight : 20,
                'final' => $config ? (float) $config->final_weight : 40,
            ],
            'students' => $rows,
            'notice'   => $notice,
            'notice_message' => $noticeMessage,
        ]);
    }
}
