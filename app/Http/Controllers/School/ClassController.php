<?php

namespace App\Http\Controllers\School;

use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ClassController extends BaseSchoolController
{
    public function index()
    {
        $classes = SchoolClass::withCount('students')
            ->orderBy('level_order')
            ->orderBy('grade')
            ->orderBy('section')
            ->get();

        return Inertia::render('School/Classes/Index', [
            'classes' => $classes->map(fn ($c) => [
                'id'             => $c->id,
                'grade'          => $c->grade,
                'section'        => $c->section,
                'display_name'   => $c->display_name,
                'academic_year'  => $c->academic_year,
                'students_count' => $c->students_count,
                'is_active'      => (bool) $c->is_active,
            ]),
            'is_admin' => auth()->user()?->isSchoolAdmin() ?? false,
        ]);
    }

    public function create()
    {
        $this->requireAdmin();

        return Inertia::render('School/Classes/Form', [
            'schoolClass'   => null,
            'academic_year' => $this->currentSchool()->academic_year,
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAdmin();

        $validated = $request->validate($this->rules());
        $validated['is_active'] = true;
        $validated['level_order'] = $validated['level_order'] ?? 0;

        SchoolClass::create($validated);

        return redirect()->route('school.classes.index')->with('success', 'Class created successfully.');
    }

    public function edit(SchoolClass $class)
    {
        $this->requireAdmin();

        return Inertia::render('School/Classes/Form', [
            'schoolClass' => [
                'id'            => $class->id,
                'grade'         => $class->grade,
                'section'       => $class->section,
                'academic_year' => $class->academic_year,
                'level_order'   => $class->level_order,
                'is_active'     => (bool) $class->is_active,
                'display_name'  => $class->display_name,
            ],
            'academic_year' => $class->academic_year,
        ]);
    }

    public function update(Request $request, SchoolClass $class)
    {
        $this->requireAdmin();

        $validated = $request->validate($this->rules($class->id) + [
            'is_active' => 'boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['level_order'] = $validated['level_order'] ?? 0;

        $class->update($validated);

        return redirect()->route('school.classes.index')->with('success', 'Class updated.');
    }

    /**
     * Validation rules. A class is uniquely identified by grade + section +
     * academic year within a school, enforced here (scoped to current school).
     */
    private function rules(?int $ignoreId = null): array
    {
        $unique = Rule::unique('classes')
            ->where('school_id', $this->schoolId())
            ->where('section', request('section'))
            ->where('academic_year', request('academic_year'))
            ->ignore($ignoreId);

        return [
            'grade'         => ['required', 'string', 'max:50', $unique],
            'section'       => 'required|string|max:10',
            'academic_year' => 'required|string|max:20',
            'level_order'   => 'nullable|integer|min:0',
        ];
    }
}
