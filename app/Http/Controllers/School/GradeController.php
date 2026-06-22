<?php

namespace App\Http\Controllers\School;

use App\Models\AssessmentConfig;
use App\Models\AuditLog;
use App\Models\ClassSubject;
use App\Models\GradeSubmission;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GradeController extends BaseSchoolController
{
    private const ASSESSMENT_TYPES  = ['ca', 'midterm', 'final'];
    private const ASSESSMENT_LABELS = ['ca' => 'CA', 'midterm' => 'Midterm', 'final' => 'Final'];

    // ── Index: class/term selector + subject × assessment matrix ──────────

    public function index(Request $request)
    {
        $classes = $this->classesForCurrentUser();
        $terms   = Term::orderBy('order')->get();

        $class  = null;
        $term   = null;
        $matrix = [];

        $filters = $request->validate([
            'class_id' => ['nullable', Rule::exists('classes', 'id')->where('school_id', $this->schoolId())],
            'term_id'  => ['nullable', Rule::exists('terms', 'id')->where('school_id', $this->schoolId())],
        ]);

        if (!empty($filters['class_id']) && !empty($filters['term_id'])) {
            $this->requireTeacherCanAccessClass((int) $filters['class_id']);
            $class = SchoolClass::findOrFail($filters['class_id']);
            $term  = Term::findOrFail($filters['term_id']);

            $classSubjects = ClassSubject::where('class_id', $class->id)
                ->with('subject', 'teacher')
                ->get();

            $totalStudents = Student::where('class_id', $class->id)
                ->where('status', 'active')
                ->count();

            // One query: counts per subject/type/status
            $gradeCounts = StudentGrade::where('class_id', $class->id)
                ->where('term_id', $term->id)
                ->selectRaw('subject_id, assessment_type, status, COUNT(*) as cnt')
                ->groupBy('subject_id', 'assessment_type', 'status')
                ->get()
                ->groupBy('subject_id');

            foreach ($classSubjects as $cs) {
                $typeData = [];
                foreach (self::ASSESSMENT_TYPES as $type) {
                    $byStatus = collect($gradeCounts[$cs->subject_id] ?? [])
                        ->where('assessment_type', $type)
                        ->keyBy('status');

                    $published = (int) ($byStatus['published']->cnt ?? 0);
                    $draft     = (int) ($byStatus['draft']->cnt ?? 0);
                    $entered   = $published + $draft;

                    if ($totalStudents > 0 && $published >= $totalStudents) {
                        $status = 'published';
                    } elseif ($published > 0 || $draft > 0) {
                        $status = $published > 0 ? 'partial' : 'draft';
                    } else {
                        $status = 'not_started';
                    }

                    $typeData[$type] = [
                        'status'  => $status,
                        'entered' => $entered,
                        'total'   => $totalStudents,
                    ];
                }

                $matrix[] = [
                    'class_subject' => $cs,
                    'subject'       => $cs->subject,
                    'types'         => $typeData,
                ];
            }
        }

        return view('school.grades.index', compact(
            'classes', 'terms', 'class', 'term', 'matrix', 'filters'
        ));
    }

    // ── Enter: grade entry form for one subject/term/assessment_type ───────

    public function enter(Request $request)
    {
        $this->requireCanEnter();

        $filters = $request->validate([
            'class_id'        => ['required', Rule::exists('classes', 'id')->where('school_id', $this->schoolId())],
            'subject_id'      => ['required', Rule::exists('subjects', 'id')->where('school_id', $this->schoolId())],
            'term_id'         => ['required', Rule::exists('terms', 'id')->where('school_id', $this->schoolId())],
            'assessment_type' => ['required', Rule::in(self::ASSESSMENT_TYPES)],
        ]);

        $this->requireTeacherCanAccessClass((int) $filters['class_id']);

        // The subject must actually be taught in this class. Otherwise grades
        // would be orphaned — the gradebook iterates ClassSubject and would
        // never surface them.
        $this->requireSubjectInClass($filters['class_id'], $filters['subject_id']);

        $class   = SchoolClass::findOrFail($filters['class_id']);
        $subject = Subject::findOrFail($filters['subject_id']);
        $term    = Term::findOrFail($filters['term_id']);

        $students = Student::where('class_id', $class->id)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();

        $existing = StudentGrade::where('class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->where('assessment_type', $filters['assessment_type'])
            ->get()
            ->keyBy('student_id');

        $isLocked = $existing->contains('status', 'published');

        $config = AssessmentConfig::where('school_id', $this->schoolId())
            ->where('academic_year', $term->academic_year)
            ->whereNull('grade_level')
            ->first();

        return view('school.grades.enter', [
            'class'          => $class,
            'subject'        => $subject,
            'term'           => $term,
            'assessmentType' => $filters['assessment_type'],
            'assessmentLabel'=> self::ASSESSMENT_LABELS[$filters['assessment_type']],
            'students'       => $students,
            'existing'       => $existing,
            'isLocked'       => $isLocked,
            'config'         => $config,
        ]);
    }

    // ── Save: upsert grades as draft ────────────────────────────────────────

    public function save(Request $request)
    {
        $this->requireCanEnter();
        $this->requireWritableSubscription();

        $validated = $request->validate([
            'class_id'             => ['required', Rule::exists('classes', 'id')->where('school_id', $this->schoolId())],
            'subject_id'           => ['required', Rule::exists('subjects', 'id')->where('school_id', $this->schoolId())],
            'term_id'              => ['required', Rule::exists('terms', 'id')->where('school_id', $this->schoolId())],
            'assessment_type'      => ['required', Rule::in(self::ASSESSMENT_TYPES)],
            'grades'               => ['required', 'array', 'min:1'],
            'grades.*.student_id'  => ['required', Rule::exists('students', 'id')->where('school_id', $this->schoolId())],
            'grades.*.score'       => ['required', 'numeric', 'min:0', 'max:100'],
            'grades.*.max_score'   => ['required', 'numeric', 'min:1', 'max:100'],
        ]);

        $this->requireTeacherCanAccessClass((int) $validated['class_id']);

        // The subject must be taught in this class (no orphan grades).
        $this->requireSubjectInClass($validated['class_id'], $validated['subject_id']);

        // A score can never exceed its own max — otherwise the weighted
        // percentage exceeds 100% and silently corrupts every average/rank.
        foreach ($validated['grades'] as $i => $item) {
            if ((float) $item['score'] > (float) $item['max_score']) {
                return back()
                    ->withInput()
                    ->withErrors(["grades.$i.score" => 'Score cannot exceed its maximum.']);
            }
        }

        // Do not overwrite published grades
        if (StudentGrade::where('class_id', $validated['class_id'])
            ->where('subject_id', $validated['subject_id'])
            ->where('term_id', $validated['term_id'])
            ->where('assessment_type', $validated['assessment_type'])
            ->where('status', 'published')
            ->exists()) {
            return back()->withErrors(['grades' => 'These grades have been published and cannot be modified.']);
        }

        DB::transaction(function () use ($validated) {
            foreach ($validated['grades'] as $item) {
                StudentGrade::updateOrCreate(
                    [
                        'school_id'       => $this->schoolId(),
                        'student_id'      => $item['student_id'],
                        'subject_id'      => $validated['subject_id'],
                        'term_id'         => $validated['term_id'],
                        'assessment_type' => $validated['assessment_type'],
                    ],
                    [
                        'class_id'   => $validated['class_id'],
                        'score'      => $item['score'],
                        'max_score'  => $item['max_score'],
                        'entered_by' => auth()->id(),
                        'status'     => 'draft',
                    ]
                );
            }
        });

        AuditLog::log('grades.saved', 'student_grades', null, null, [
            'class_id'        => $validated['class_id'],
            'subject_id'      => $validated['subject_id'],
            'term_id'         => $validated['term_id'],
            'assessment_type' => $validated['assessment_type'],
            'count'           => count($validated['grades']),
        ]);

        return redirect()
            ->route('school.grades.index', [
                'class_id' => $validated['class_id'],
                'term_id'  => $validated['term_id'],
            ])
            ->with('success', 'Grades saved successfully.');
    }

    // ── Publish: admin publishes draft grades ──────────────────────────────

    public function publish(Request $request)
    {
        $this->requireAdmin();

        $validated = $request->validate([
            'class_id'        => ['required', Rule::exists('classes', 'id')->where('school_id', $this->schoolId())],
            'subject_id'      => ['required', Rule::exists('subjects', 'id')->where('school_id', $this->schoolId())],
            'term_id'         => ['required', Rule::exists('terms', 'id')->where('school_id', $this->schoolId())],
            'assessment_type' => ['required', Rule::in(self::ASSESSMENT_TYPES)],
        ]);

        // Nothing to publish — refuse rather than stamp an empty submission.
        $draftCount = StudentGrade::where('class_id', $validated['class_id'])
            ->where('subject_id', $validated['subject_id'])
            ->where('term_id', $validated['term_id'])
            ->where('assessment_type', $validated['assessment_type'])
            ->where('status', 'draft')
            ->count();

        if ($draftCount === 0) {
            return back()->withErrors(['publish' => 'There are no draft grades to publish for this assessment.']);
        }

        DB::transaction(function () use ($validated) {
            StudentGrade::where('class_id', $validated['class_id'])
                ->where('subject_id', $validated['subject_id'])
                ->where('term_id', $validated['term_id'])
                ->where('assessment_type', $validated['assessment_type'])
                ->where('status', 'draft')
                ->update(['status' => 'published', 'published_at' => now()]);

            GradeSubmission::updateOrCreate(
                [
                    'school_id'  => $this->schoolId(),
                    'class_id'   => $validated['class_id'],
                    'subject_id' => $validated['subject_id'],
                    'term_id'    => $validated['term_id'],
                ],
                [
                    'published_by' => auth()->id(),
                    'published_at' => now(),
                ]
            );
        });

        AuditLog::log('grades.published', 'grade_submissions', null, null, $validated);

        return redirect()
            ->route('school.grades.index', [
                'class_id' => $validated['class_id'],
                'term_id'  => $validated['term_id'],
            ])
            ->with('success', 'Grades published.');
    }

    // ── Gradebook: full class × subject matrix ─────────────────────────────

    public function gradebook(Request $request)
    {
        $filters = $request->validate([
            'class_id' => ['required', Rule::exists('classes', 'id')->where('school_id', $this->schoolId())],
            'term_id'  => ['required', Rule::exists('terms', 'id')->where('school_id', $this->schoolId())],
        ]);

        $this->requireTeacherCanAccessClass((int) $filters['class_id']);
        $class = SchoolClass::findOrFail($filters['class_id']);
        $term  = Term::findOrFail($filters['term_id']);

        $students = Student::where('class_id', $class->id)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();

        $classSubjects = ClassSubject::where('class_id', $class->id)
            ->with('subject')
            ->get();

        // Bulk-load all grades for this class/term (all statuses for live view)
        $allGrades = StudentGrade::where('class_id', $class->id)
            ->where('term_id', $term->id)
            ->get()
            ->groupBy('student_id');

        // Load assessment config once
        $config = AssessmentConfig::where('school_id', $this->schoolId())
            ->where('academic_year', $term->academic_year)
            ->whereNull('grade_level')
            ->first();

        $caW  = $config ? (float) $config->ca_weight / 100 : 0.40;
        $midW = $config ? (float) $config->midterm_weight / 100 : 0.20;
        $finW = $config ? (float) $config->final_weight / 100 : 0.40;

        // Build matrix rows
        $rows = [];
        foreach ($students as $student) {
            $studentGrades = $allGrades[$student->id] ?? collect();
            $subjectTotals = [];

            foreach ($classSubjects as $cs) {
                $byType = $studentGrades->where('subject_id', $cs->subject_id)->keyBy('assessment_type');

                $ca     = $byType['ca'] ?? null;
                $mid    = $byType['midterm'] ?? null;
                $fin    = $byType['final'] ?? null;

                if ($ca && $mid && $fin) {
                    $caScore  = (float) $ca->score  / (float) $ca->max_score  * 100;
                    $midScore = (float) $mid->score / (float) $mid->max_score * 100;
                    $finScore = (float) $fin->score / (float) $fin->max_score * 100;
                    $total = round($caScore * $caW + $midScore * $midW + $finScore * $finW, 1);
                    $allPublished = ($ca->status === 'published' && $mid->status === 'published' && $fin->status === 'published');
                } else {
                    $total = null;
                    $allPublished = false;
                }

                $subjectTotals[$cs->subject_id] = [
                    'total'        => $total,
                    'published'    => $allPublished,
                    'ca'           => $ca,
                    'midterm'      => $mid,
                    'final'        => $fin,
                ];
            }

            $validTotals = array_filter(array_column($subjectTotals, 'total'), fn($t) => $t !== null);
            $termAvg = count($validTotals) > 0 ? round(array_sum($validTotals) / count($validTotals), 1) : null;

            $rows[] = [
                'student'        => $student,
                'subject_totals' => $subjectTotals,
                'term_avg'       => $termAvg,
                'rank'           => null,
            ];
        }

        // Sort by term_avg desc and assign ranks
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
            if ($row['term_avg'] === null) { continue; }
            $pos++;
            if ($row['term_avg'] !== $prevAvg) { $rank = $pos; }
            $row['rank'] = $rank;
            $prevAvg = $row['term_avg'];
        }
        unset($row);

        return view('school.grades.gradebook', compact('class', 'term', 'rows', 'classSubjects'));
    }

    // ── Term management (simple CRUD) ──────────────────────────────────────

    public function terms()
    {
        $this->requireAdmin();
        $terms = Term::orderBy('order')->get();
        return view('school.grades.terms', compact('terms'));
    }

    public function termStore(Request $request)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:50'],
            'academic_year' => ['required', 'string', 'max:20'],
            'start_date'    => ['required', 'date'],
            'end_date'      => ['required', 'date', 'after:start_date'],
            'order'         => ['required', 'integer', 'min:1', 'max:10'],
            'weight'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_current'    => ['boolean'],
        ]);

        $validated['school_id'] = $this->schoolId();
        $validated['is_current'] = $request->boolean('is_current');
        $validated['is_weight_locked'] = false;

        if ($validated['is_current']) {
            Term::where('school_id', $this->schoolId())->update(['is_current' => false]);
        }

        Term::create($validated);

        return redirect()->route('school.grades.terms')->with('success', 'Term created.');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function requireCanEnter(): void
    {
        $user = auth()->user();
        if (! $user->isSchoolAdmin() && ! $user->isTeacher()) {
            abort(403, 'Only administrators and teachers can enter grades.');
        }
    }

    /**
     * Ensure the subject is actually assigned to the class. Grades recorded for
     * a subject not on the class roster would be invisible in the gradebook
     * (which iterates ClassSubject) and skew nothing visibly — a silent orphan.
     */
    private function requireSubjectInClass(int $classId, int $subjectId): void
    {
        $exists = ClassSubject::where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->exists();

        if (! $exists) {
            abort(422, 'This subject is not assigned to the selected class.');
        }
    }
}
