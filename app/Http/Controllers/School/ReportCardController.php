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
use Inertia\Inertia;

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

        return Inertia::render('School/ReportCards/Index', [
            'classes'  => $classes->map(fn ($c) => ['id' => $c->id, 'name' => $c->display_name]),
            'terms'    => $terms->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'academic_year' => $t->academic_year]),
            'filters'  => ['class_id' => $filters['class_id'] ?? null, 'term_id' => $filters['term_id'] ?? null],
            'class'    => $class ? ['id' => $class->id, 'name' => $class->display_name] : null,
            'term'     => $term ? ['id' => $term->id, 'name' => $term->name, 'academic_year' => $term->academic_year] : null,
            'students' => $students->map(fn ($s) => [
                'id'          => $s->id,
                'full_name'   => $s->full_name,
                'full_name_am' => $s->full_name_am,
                'uid'         => $s->uid,
                'photo_url'   => $s->photo_url,
                'initials'    => strtoupper(substr($s->first_name, 0, 1) . substr($s->last_name, 0, 1)),
            ]),
        ]);
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

        $weightLabels = [
            'ca'      => 'CA (' . round($caW * 100) . '%)',
            'midterm' => 'Midterm (' . round($midW * 100) . '%)',
            'final'   => 'Final (' . round($finW * 100) . '%)',
        ];

        return Inertia::render('School/ReportCards/Show', [
            'student' => [
                'id'          => $student->id,
                'full_name'   => $student->full_name,
                'full_name_am' => $student->full_name_am,
                'uid'         => $student->uid,
                'gender'      => $student->gender,
                'class_name'  => $student->schoolClass?->display_name,
                'date_of_birth' => $student->date_of_birth?->format('M d, Y'),
            ],
            'term'     => ['id' => $term->id, 'name' => $term->name, 'academic_year' => $term->academic_year],
            'school'   => ['name' => $school->name, 'address' => $school->address ?? null],
            'term_avg' => $termAvg,
            'rank'     => $rank,
            'class_size' => $classSize,
            'weight_labels' => $weightLabels,
            'subject_rows' => array_map(fn ($row) => [
                'subject_name' => $row['subject']->name,
                'ca'     => $row['ca']  ? ['score' => (float)$row['ca']->score,  'max' => (float)$row['ca']->max_score]  : null,
                'midterm'=> $row['midterm'] ? ['score' => (float)$row['midterm']->score, 'max' => (float)$row['midterm']->max_score] : null,
                'final'  => $row['final'] ? ['score' => (float)$row['final']->score, 'max' => (float)$row['final']->max_score] : null,
                'total'  => $row['total'],
                'letter' => $row['letter'],
            ], $subjectRows),
            'generated_at' => now()->format('M d, Y'),
        ]);
    }
}
