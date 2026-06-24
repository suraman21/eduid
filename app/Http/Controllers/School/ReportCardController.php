<?php

namespace App\Http\Controllers\School;

use App\Models\AssessmentConfig;
use App\Models\ClassSubject;
use App\Models\GradingScale;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportCardController extends BaseSchoolController
{
    public function index(Request $request)
    {
        $classes = $this->classesForCurrentUser();
        $terms = Term::orderBy('order')->get();

        $filters = $request->validate([
            'class_id' => ['nullable', Rule::exists('classes', 'id')->where('school_id', $this->schoolId())],
            'term_id' => ['nullable', Rule::exists('terms', 'id')->where('school_id', $this->schoolId())],
        ]);

        $students = collect();
        $class = null;
        $term = null;

        if (! empty($filters['class_id']) && ! empty($filters['term_id'])) {
            $this->requireTeacherCanAccessClass((int) $filters['class_id']);

            $class = SchoolClass::findOrFail($filters['class_id']);
            $term = Term::findOrFail($filters['term_id']);

            $students = Student::where('class_id', $class->id)
                ->where('status', 'active')
                ->orderBy('first_name')
                ->get();
        }

        return view('school.report_cards.index', compact('classes', 'terms', 'filters', 'students', 'class', 'term'));
    }

    public function show(Student $student, Request $request)
    {
        $termId = $request->validate([
            'term_id' => ['required', Rule::exists('terms', 'id')->where('school_id', $this->schoolId())],
        ])['term_id'];

        if ($student->class_id) {
            $this->requireTeacherCanAccessClass($student->class_id);
        }

        $term = Term::findOrFail($termId);
        $student->load('schoolClass', 'guardians');

        $classSubjects = $student->class_id
            ? ClassSubject::where('class_id', $student->class_id)->with('subject')->get()
            : collect();

        // All published grades for this student + term
        $gradesRaw = StudentGrade::where('student_id', $student->id)
            ->where('term_id', $termId)
            ->where('status', 'published')
            ->get()
            ->groupBy('subject_id');

        $config = AssessmentConfig::where('school_id', $this->schoolId())
            ->where('academic_year', $term->academic_year)
            ->whereNull('grade_level')
            ->first();

        $caW = $config ? (float) $config->ca_weight / 100 : 0.40;
        $midW = $config ? (float) $config->midterm_weight / 100 : 0.20;
        $finW = $config ? (float) $config->final_weight / 100 : 0.40;

        $scales = GradingScale::orderByDesc('min_score')->get();

        $subjectRows = [];
        foreach ($classSubjects as $cs) {
            $byType = collect($gradesRaw[$cs->subject_id] ?? [])->keyBy('assessment_type');

            $ca = $byType['ca'] ?? null;
            $mid = $byType['midterm'] ?? null;
            $fin = $byType['final'] ?? null;

            $total = null;
            if ($ca && $mid && $fin
                && (float) $ca->max_score > 0
                && (float) $mid->max_score > 0
                && (float) $fin->max_score > 0) {
                $caScore = (float) $ca->score / (float) $ca->max_score * 100;
                $midScore = (float) $mid->score / (float) $mid->max_score * 100;
                $finScore = (float) $fin->score / (float) $fin->max_score * 100;
                $total = round($caScore * $caW + $midScore * $midW + $finScore * $finW, 1);
            }

            $letter = null;
            if ($total !== null) {
                foreach ($scales as $scale) {
                    if ($scale->matches($total)) {
                        $letter = $scale->label;
                        break;
                    }
                }
            }

            $subjectRows[] = [
                'subject' => $cs->subject,
                'ca' => $ca,
                'midterm' => $mid,
                'final' => $fin,
                'total' => $total,
                'letter' => $letter,
            ];
        }

        $validTotals = array_filter(array_column($subjectRows, 'total'), fn ($t) => $t !== null);
        $termAvg = count($validTotals) > 0
            ? round(array_sum($validTotals) / count($validTotals), 1)
            : null;

        // Compute rank among classmates (published grades only).
        // All classmate grades are loaded in a SINGLE query and grouped in PHP —
        // previously this ran one query per classmate (N+1) (PERF-001 / SCALE-02).
        $rank = null;
        $classSize = null;
        if ($student->class_id && $termAvg !== null) {
            $allStudentIds = Student::where('class_id', $student->class_id)
                ->where('status', 'active')
                ->pluck('id');

            $classSize = $allStudentIds->count();

            // One bulk load of every published grade for the whole class + term,
            // grouped by student then by subject.
            $allGrades = StudentGrade::whereIn('student_id', $allStudentIds)
                ->where('term_id', $termId)
                ->where('status', 'published')
                ->get()
                ->groupBy('student_id');

            // Count how many classmates have a strictly better average.
            $betterCount = 0;
            foreach ($allStudentIds as $sid) {
                if ($sid === $student->id) {
                    continue;
                }
                $peerGrades = collect($allGrades[$sid] ?? [])->groupBy('subject_id');

                $peerTotals = [];
                foreach ($classSubjects as $cs) {
                    $bt = collect($peerGrades[$cs->subject_id] ?? [])->keyBy('assessment_type');
                    $pCa = $bt['ca'] ?? null;
                    $pMid = $bt['midterm'] ?? null;
                    $pFin = $bt['final'] ?? null;
                    if ($pCa && $pMid && $pFin
                        && (float) $pCa->max_score > 0
                        && (float) $pMid->max_score > 0
                        && (float) $pFin->max_score > 0) {
                        $peerTotals[] = round(
                            ((float) $pCa->score / (float) $pCa->max_score * 100) * $caW +
                            ((float) $pMid->score / (float) $pMid->max_score * 100) * $midW +
                            ((float) $pFin->score / (float) $pFin->max_score * 100) * $finW,
                            1
                        );
                    }
                }
                if (count($peerTotals) > 0) {
                    $peerAvg = array_sum($peerTotals) / count($peerTotals);
                    if ($peerAvg > $termAvg) {
                        $betterCount++;
                    }
                }
            }
            $rank = $betterCount + 1;
        }

        $school = $this->currentSchool();

        return view('school.report_cards.show', compact(
            'student', 'term', 'subjectRows', 'termAvg', 'rank', 'classSize', 'school', 'config'
        ));
    }
}
