<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StudentController extends Controller
{
    private function schoolId(): int
    {
        return Auth::user()->school_id;
    }

    public function index(Request $request)
    {
        $query = Student::where('school_id', $this->schoolId())
            ->with('schoolClass');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('uid', 'like', "%{$search}%");
            });
        }

        if ($classId = $request->get('class_id')) {
            $query->where('class_id', $classId);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $students = $query->orderBy('first_name')->paginate(20);
        $classes  = SchoolClass::where('school_id', $this->schoolId())->where('is_active', true)->orderBy('grade')->get();

        return view('school.students.index', compact('students', 'classes'));
    }

    public function create()
    {
        $classes = SchoolClass::where('school_id', $this->schoolId())->where('is_active', true)->orderBy('grade')->get();
        return view('school.students.form', ['student' => new Student(), 'classes' => $classes]);
    }

    public function store(Request $request)
    {
        $school = Auth::user()->school;

        if (!$school->canAddNewData()) {
            return back()->with('error', 'New data entry is restricted. Your subscription has expired.');
        }

        if ($school->hasReachedStudentLimit()) {
            return back()->with('error', "Student limit ({$school->max_students}) reached. Upgrade your plan.");
        }

        $validated = $this->validateStudent($request);
        $validated['school_id'] = $this->schoolId();
        $validated['uid'] = $this->generateUid();
        $validated['status'] = $validated['status'] ?? 'active';
        $validated['join_date'] = $validated['join_date'] ?? today()->toDateString();

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store("schools/{$this->schoolId()}/students", 'public');
        }

        Student::create($validated);

        return redirect()->route('school.students.index')->with('success', 'Student added successfully.');
    }

    public function show(Student $student)
    {
        $this->authorizeStudent($student);
        $student->load('schoolClass', 'guardians');
        return view('school.students.show', compact('student'));
    }

    public function edit(Student $student)
    {
        $this->authorizeStudent($student);
        $classes = SchoolClass::where('school_id', $this->schoolId())->where('is_active', true)->orderBy('grade')->get();
        return view('school.students.form', compact('student', 'classes'));
    }

    public function update(Request $request, Student $student)
    {
        $this->authorizeStudent($student);

        $validated = $this->validateStudent($request, $student->id);

        if ($request->hasFile('photo')) {
            if ($student->photo_path) {
                Storage::disk('public')->delete($student->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store("schools/{$this->schoolId()}/students", 'public');
        }

        $student->update($validated);

        return redirect()->route('school.students.show', $student)->with('success', 'Student updated.');
    }

    private function validateStudent(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'first_name'    => 'required|string|max:100',
            'last_name'     => 'required|string|max:100',
            'first_name_am' => 'nullable|string|max:100',
            'last_name_am'  => 'nullable|string|max:100',
            'gender'        => 'required|in:male,female',
            'date_of_birth' => 'nullable|date|before:today',
            'class_id'      => 'nullable|exists:classes,id',
            'blood_type'    => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'medical_notes' => 'nullable|string|max:500',
            'enrollment_date' => 'nullable|date',
            'status'        => 'nullable|in:active,graduated,withdrawn,transferred',
            'join_date'     => 'nullable|date',
            'end_date'      => 'nullable|date|after_or_equal:join_date',
            'photo'         => 'nullable|image|max:2048',
        ]);
    }

    private function generateUid(): string
    {
        do {
            $uid = strtoupper(Str::random(8));
        } while (Student::where('uid', $uid)->exists());
        return $uid;
    }

    private function authorizeStudent(Student $student): void
    {
        if ($student->school_id !== $this->schoolId()) {
            abort(403);
        }
    }
}
