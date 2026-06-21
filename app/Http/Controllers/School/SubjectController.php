<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubjectController extends Controller
{
    private function schoolId(): int
    {
        return Auth::user()->school_id;
    }

    public function index()
    {
        $subjects = Subject::where('school_id', $this->schoolId())
            ->orderBy('name')
            ->get();

        return view('school.subjects.index', compact('subjects'));
    }

    public function store(Request $request)
    {
        $schoolId = $this->schoolId();

        $validated = $request->validate([
            'name'  => 'required|string|max:100',
            'code'  => [
                'nullable', 'string', 'max:20',
                \Illuminate\Validation\Rule::unique('subjects')->where('school_id', $schoolId),
            ],
            'color' => 'nullable|string|max:7',
        ]);

        $validated['school_id'] = $schoolId;
        $validated['is_active'] = true;

        Subject::create($validated);

        return redirect()->route('school.subjects.index')->with('success', 'Subject added.');
    }

    public function update(Request $request, Subject $subject)
    {
        $this->authorizeSubject($subject);

        $validated = $request->validate([
            'name'      => 'required|string|max:100',
            'code'      => [
                'nullable', 'string', 'max:20',
                \Illuminate\Validation\Rule::unique('subjects')->where('school_id', $this->schoolId())->ignore($subject->id),
            ],
            'color'     => 'nullable|string|max:7',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $subject->update($validated);

        return redirect()->route('school.subjects.index')->with('success', 'Subject updated.');
    }

    private function authorizeSubject(Subject $subject): void
    {
        if ($subject->school_id !== $this->schoolId()) abort(403);
    }
}
