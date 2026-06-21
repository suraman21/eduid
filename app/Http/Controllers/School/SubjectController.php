<?php

namespace App\Http\Controllers\School;

use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubjectController extends BaseSchoolController
{
    public function index()
    {
        $subjects = Subject::orderBy('name')->get();

        return view('school.subjects.index', compact('subjects'));
    }

    public function store(Request $request)
    {
        $this->requireAdmin();

        $validated = $request->validate($this->rules());

        Subject::create($validated + ['is_active' => true]);

        return redirect()->route('school.subjects.index')->with('success', 'Subject added.');
    }

    public function update(Request $request, Subject $subject)
    {
        $this->requireAdmin();

        $validated = $request->validate($this->rules($subject->id) + [
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $subject->update($validated);

        return redirect()->route('school.subjects.index')->with('success', 'Subject updated.');
    }

    /**
     * Validation rules. Subject code is unique per school (composite unique
     * index), so scope the unique rule to the current school.
     */
    private function rules(?int $ignoreId = null): array
    {
        return [
            'name'  => 'required|string|max:100',
            'code'  => [
                'required', 'string', 'max:20',
                Rule::unique('subjects')->where('school_id', $this->schoolId())->ignore($ignoreId),
            ],
            'color' => 'nullable|string|max:7',
        ];
    }
}
