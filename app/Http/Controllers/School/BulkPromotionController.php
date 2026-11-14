<?php

namespace App\Http\Controllers\School;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\EnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * End-of-year promotion for a whole cohort at once.
 *
 * The old YearTransitionService rewrote class mappings and lost history. This
 * does the same job per student: each one gets their current enrolment closed
 * with an outcome and a new one opened, so the year that just ended survives.
 *
 * Every student is processed in their own transaction — one bad row (no
 * enrolment, missing target class) cannot roll back the other 39.
 */
class BulkPromotionController extends BaseSchoolController
{
    public function __construct(private EnrollmentService $enrollments)
    {
    }

    /** The planning screen: pick a class, see its students and proposed moves. */
    public function index(Request $request)
    {
        $school = $this->currentSchool();

        $classes = SchoolClass::query()
            ->where('school_id', $school->id)
            ->with('programme:id,name,code')
            ->withCount([
                'enrollments as current_count' => fn ($q) => $q->where('is_current', true),
            ])
            ->orderBy('level_order')
            ->orderBy('grade')
            ->orderBy('section')
            ->get();

        $classOptions = $classes->map(fn ($c) => [
            'id'            => $c->id,
            'label'         => $c->lifecycle_label,
            'level_number'  => $c->level_number,
            'programme_id'  => $c->programme_id,
            'programme'     => $c->programme?->name,
            'academic_year' => $c->academic_year,
            'current_count' => (int) $c->current_count,
            'is_active'     => (bool) $c->is_active,
        ]);

        $sourceId = $request->integer('class_id') ?: null;
        $students = [];

        if ($sourceId) {
            $source = $classes->firstWhere('id', $sourceId);

            if ($source) {
                // Next level up inside the same programme — the sensible default.
                $suggested = $classes
                    ->where('programme_id', $source->programme_id)
                    ->filter(fn ($c) => (int) $c->level_number === (int) $source->level_number + 1)
                    ->first();

                $students = StudentEnrollment::query()
                    ->where('class_id', $source->id)
                    ->where('is_current', true)
                    ->with(['student:id,uid,first_name,last_name,photo_path,gender'])
                    ->get()
                    ->map(fn ($e) => [
                        'student_id'      => $e->student_id,
                        'enrollment_id'   => $e->id,
                        'name'            => $e->student?->full_name ?? '—',
                        'uid'             => $e->student?->uid,
                        'gender'          => $e->student?->gender,
                        'enrolled_on'     => $e->enrolled_on?->format('Y-m-d'),
                        'suggested_action' => $suggested ? 'promote' : 'graduate',
                        'suggested_target' => $suggested?->id,
                    ])
                    ->values()
                    ->all();
            }
        }

        return Inertia::render('School/Promotions/Index', [
            'classes'        => $classOptions,
            'source_class_id'=> $sourceId,
            'students'       => $students,
            'academic_year'  => $school->academic_year,
            'is_admin'       => auth()->user()?->isSchoolAdmin() ?? false,
        ]);
    }

    /** Apply the plan. */
    public function store(Request $request)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $data = $request->validate([
            'actions'                 => ['required', 'array', 'min:1'],
            'actions.*.student_id'    => ['required', 'integer'],
            'actions.*.action'        => ['required', Rule::in(['promote', 'repeat', 'graduate', 'skip'])],
            'actions.*.target_class_id' => ['nullable', 'integer'],
            'note'                    => ['nullable', 'string', 'max:500'],
        ]);

        $schoolId = $this->schoolId();
        $note = $data['note'] ?? null;

        // Only classes owned by this school may be targeted.
        $allowedClassIds = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->pluck('id')
            ->all();

        $done = 0;
        $skipped = 0;
        $failed = [];

        foreach ($data['actions'] as $row) {
            if ($row['action'] === 'skip') {
                $skipped++;
                continue;
            }

            /** @var Student|null $student */
            $student = Student::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->find($row['student_id']);

            if (!$student) {
                $failed[] = "Student #{$row['student_id']} not found.";
                continue;
            }

            try {
                if ($row['action'] === 'graduate') {
                    $this->enrollments->graduate($student, $note);

                } elseif ($row['action'] === 'repeat') {
                    $target = !empty($row['target_class_id'])
                        ? SchoolClass::find($row['target_class_id'])
                        : null;
                    $this->enrollments->repeat($student, $target, $note);

                } else { // promote
                    $targetId = (int) ($row['target_class_id'] ?? 0);

                    if (!$targetId || !in_array($targetId, $allowedClassIds, true)) {
                        $failed[] = "{$student->full_name}: no valid target class.";
                        continue;
                    }

                    $this->enrollments->promote($student, SchoolClass::findOrFail($targetId), $note);
                }

                $done++;
            } catch (\Throwable $e) {
                $failed[] = "{$student->full_name}: " . $e->getMessage();
            }
        }

        $message = "{$done} student" . ($done === 1 ? '' : 's') . " processed";

        if ($skipped) {
            $message .= ", {$skipped} skipped";
        }
        if ($failed) {
            $message .= ', ' . count($failed) . ' failed';
        }
        $message .= '.';

        return back()->with('success', $message)->with('bulk_failures', array_slice($failed, 0, 20));
    }
}
