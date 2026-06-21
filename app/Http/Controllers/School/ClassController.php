<?php

namespace App\Http\Controllers\School;

use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassController extends BaseSchoolController
{
    public function index()
    {
        $classes = SchoolClass::withCount('students')
            ->orderBy('level_order')
            ->orderBy('grade')
            ->orderBy('section')
            ->get();

        return view('school.classes.index', compact('classes'));
    }

    public function create()
    {
        $this->requireAdmin();

        return view('school.classes.form', ['class' => new SchoolClass()]);
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

        return view('school.classes.form', compact('class'));
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
