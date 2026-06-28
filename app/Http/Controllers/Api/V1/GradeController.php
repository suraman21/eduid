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

        $termId = request()->query('term_id');
        $term = $termId
            ? Term::where('id', $termId)->firstOrFail()
            : Term::where('is_current', true)->first();

        if (! $term) {
            return response()->json(['message' => 'No current term found.'], 404);
        }

        $school = $this->currentSchool();
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

        return response()->json([
            'class' => ['id' => $class->id, 'name' => $class->display_name],
            'term'  => ['id' => $term->id, 'name' => $term->name, 'academic_year' => $term->academic_year],
            'weights' => [
                'ca' => $config ? (float) $config->ca_weight : 40,
                'midterm' => $config ? (float) $config->midterm_weight : 20,
                'final' => $config ? (float) $config->final_weight : 40,
            ],
            'students' => $rows,
        ]);
    }
}
