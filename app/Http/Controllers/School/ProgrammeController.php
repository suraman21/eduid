<?php

namespace App\Http\Controllers\School;

use App\Models\Programme;
use App\Models\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Programmes of study — the layer above classes.
 *
 * A school that only ever used grade sections already has a single "General"
 * programme (created by the backfill). A college splits it into real awards;
 * a course centre makes one per course.
 */
class ProgrammeController extends BaseSchoolController
{
    public function index()
    {
        $school = $this->currentSchool();

        $programmes = Programme::query()
            ->where('school_id', $school->id)
            ->withCount('classes')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id'              => $p->id,
                'code'            => $p->code,
                'name'            => $p->name,
                'level_type'      => $p->level_type,
                'award'           => $p->award,
                'duration_months' => $p->duration_months,
                'total_levels'    => $p->total_levels,
                'description'     => $p->description,
                'is_active'       => (bool) $p->is_active,
                'classes_count'   => $p->classes_count,
                'students_count'  => StudentEnrollment::query()
                    ->where('programme_id', $p->id)
                    ->where('is_current', true)
                    ->count(),
            ]);

        return Inertia::render('School/Programmes/Index', [
            'programmes' => $programmes,
            'level_types' => [
                ['value' => 'primary',       'label' => 'Primary school'],
                ['value' => 'secondary',     'label' => 'Secondary school'],
                ['value' => 'college',       'label' => 'College'],
                ['value' => 'course_center', 'label' => 'Course centre'],
                ['value' => 'other',         'label' => 'Other'],
            ],
            'is_admin' => auth()->user()?->isSchoolAdmin() ?? false,
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAdmin();

        $school = $this->currentSchool();
        $data = $this->validated($request, $school->id);

        Programme::create(array_merge($data, ['school_id' => $school->id]));

        return back()->with('success', 'Programme created.');
    }

    public function update(Request $request, Programme $programme)
    {
        $this->requireAdmin();

        $data = $this->validated($request, $this->currentSchool()->id, $programme->id);
        $programme->update($data);

        return back()->with('success', 'Programme updated.');
    }

    public function destroy(Programme $programme)
    {
        $this->requireAdmin();

        if ($programme->classes()->exists()) {
            return back()->withErrors([
                'programme' => 'This programme still has cohorts attached. Move them first.',
            ]);
        }

        $programme->delete();

        return back()->with('success', 'Programme deleted.');
    }

    private function validated(Request $request, int $schoolId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('programmes', 'code')
                    ->where('school_id', $schoolId)
                    ->ignore($ignoreId),
            ],
            'name'            => ['required', 'string', 'max:150'],
            'level_type'      => ['required', Rule::in(['primary', 'secondary', 'college', 'course_center', 'other'])],
            'award'           => ['nullable', 'string', 'max:100'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:96'],
            'total_levels'    => ['nullable', 'integer', 'min:1', 'max:20'],
            'description'     => ['nullable', 'string', 'max:1000'],
            'is_active'       => ['boolean'],
        ]);
    }
}
