<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClassController extends Controller
{
    private function schoolId(): int
    {
        return Auth::user()->school_id;
    }

    public function index()
    {
        $classes = SchoolClass::where('school_id', $this->schoolId())
            ->withCount('students')
            ->orderBy('level_order')
            ->orderBy('grade')
            ->orderBy('section')
            ->get();

        return view('school.classes.index', compact('classes'));
    }

    public function create()
    {
        $teachers = User::where('school_id', $this->schoolId())
            ->where('role', 'teacher')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        return view('school.classes.form', ['class' => new SchoolClass(), 'teachers' => $teachers]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'grade'        => 'required|string|max:50',
            'section'      => 'required|string|max:10',
            'academic_year'=> 'required|string|max:20',
            'level_order'  => 'nullable|integer|min:0',
        ]);

        $validated['school_id'] = $this->schoolId();
        $validated['is_active'] = true;
        $validated['level_order'] = $validated['level_order'] ?? 0;

        // Prevent duplicate grade+section in same academic year
        $exists = SchoolClass::where('school_id', $this->schoolId())
            ->where('grade', $validated['grade'])
            ->where('section', $validated['section'])
            ->where('academic_year', $validated['academic_year'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['grade' => 'This class already exists for this academic year.'])->withInput();
        }

        SchoolClass::create($validated);

        return redirect()->route('school.classes.index')->with('success', 'Class created successfully.');
    }

    public function edit(SchoolClass $class)
    {
        $this->authorizeClass($class);
        $teachers = User::where('school_id', $this->schoolId())
            ->where('role', 'teacher')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        return view('school.classes.form', compact('class', 'teachers'));
    }

    public function update(Request $request, SchoolClass $class)
    {
        $this->authorizeClass($class);

        $validated = $request->validate([
            'grade'         => 'required|string|max:50',
            'section'       => 'required|string|max:10',
            'academic_year' => 'required|string|max:20',
            'level_order'   => 'nullable|integer|min:0',
            'is_active'     => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $class->update($validated);

        return redirect()->route('school.classes.index')->with('success', 'Class updated.');
    }

    private function authorizeClass(SchoolClass $class): void
    {
        if ($class->school_id !== $this->schoolId()) abort(403);
    }
}
