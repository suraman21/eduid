<?php

namespace App\Http\Controllers\School;

use App\Models\AuditLog;
use App\Models\SchoolClass;
use App\Services\YearTransitionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Academic Year Promotion wizard (admin-only ring + 'feature:year_transition'
 * at the route level). GET shows the wizard with suggested mappings; POST
 * executes atomically via YearTransitionService.
 */
class YearTransitionController extends BaseSchoolController
{
    public function __construct(private YearTransitionService $transition)
    {
    }

    public function index()
    {
        $this->requireAdmin();

        $school = $this->currentSchool();

        $classes = SchoolClass::where('is_active', true)
            ->withCount(['students as active_students' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('level_order')->orderBy('grade')->orderBy('section')
            ->get();

        $maxLevel = (int) $classes->max('level_order');

        return Inertia::render('School/YearTransition/Index', [
            'current_year'   => $school->academic_year,
            'suggested_year' => YearTransitionService::suggestNextYear($school->academic_year),
            'classes' => $classes->map(fn ($c) => [
                'id'              => $c->id,
                'name'            => $c->display_name,
                'grade'           => $c->grade,
                'section'         => $c->section,
                'level_order'     => $c->level_order,
                'active_students' => (int) $c->active_students,
                'suggested_grade' => YearTransitionService::suggestNextGrade($c->grade),
                // Highest level defaults to graduate (final year leaves).
                'suggested_action' => $maxLevel > 0 && $c->level_order === $maxLevel
                    ? 'graduate'
                    : 'promote',
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $validated = $request->validate([
            'new_year'                 => 'required|string|max:20',
            'mappings'                 => 'required|array|min:1',
            'mappings.*.class_id'      => 'required|integer',
            'mappings.*.action'        => ['required', Rule::in(YearTransitionService::ACTIONS)],
            'mappings.*.target_grade'  => 'nullable|string|max:20',
            // Explicit human confirmation — this kills every printed card.
            'confirmed'                => 'accepted',
        ]);

        $mappings = collect($validated['mappings'])
            ->keyBy(fn ($m) => (int) $m['class_id'])
            ->map(fn ($m) => [
                'action'       => $m['action'],
                'target_grade' => $m['target_grade'] ?? null,
            ])
            ->all();

        $summary = $this->transition->run($this->currentSchool(), $validated['new_year'], $mappings);

        AuditLog::log('year.transitioned', 'schools', $this->schoolId(), null, [
            'new_year' => $validated['new_year'],
        ] + $summary);

        return redirect()->route('school.dashboard')->with('success', sprintf(
            'Welcome to %s! %d student(s) promoted, %d repeated, %d graduated · %d new class(es) · %d QR tokens renewed — order new ID cards now, old cards no longer scan. Remember to re-assign teachers to the new classes.',
            $validated['new_year'],
            $summary['promoted'],
            $summary['stayed'],
            $summary['graduated'],
            $summary['new_classes'],
            $summary['tokens'],
        ));
    }
}
