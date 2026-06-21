<?php

namespace App\Http\Controllers\School;

use App\Models\Student;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StudentController extends BaseSchoolController
{
    /** Allowed student lifecycle states (matches the DB enum). */
    private const STATUSES = ['active', 'promoted', 'repeating', 'graduated', 'transferred', 'withdrawn'];

    public function index(Request $request)
    {
        $query = Student::with('schoolClass');

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
        $classes  = $this->activeClasses();

        return view('school.students.index', compact('students', 'classes'));
    }

    public function create()
    {
        $this->requireAdmin();

        return view('school.students.form', [
            'student' => new Student(),
            'classes' => $this->activeClasses(),
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $school = $this->currentSchool();
        if ($school->hasReachedStudentLimit()) {
            return back()->with('error', "Student limit ({$school->max_students}) reached. Upgrade your plan.")->withInput();
        }

        $validated = $this->validateStudent($request);
        $validated['uid']             = $this->generateUid();
        $validated['status']          = $validated['status'] ?? 'active';
        $validated['join_date']       = $validated['join_date'] ?? today()->toDateString();
        $validated['enrollment_date'] = $validated['enrollment_date'] ?? today()->toDateString();

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store("schools/{$this->schoolId()}/students", 'public');
        }

        Student::create($validated);

        return redirect()->route('school.students.index')->with('success', 'Student added successfully.');
    }

    public function show(Student $student)
    {
        $student->load('schoolClass', 'guardians');

        return view('school.students.show', compact('student'));
    }

    public function edit(Student $student)
    {
        $this->requireAdmin();

        return view('school.students.form', [
            'student' => $student,
            'classes' => $this->activeClasses(),
        ]);
    }

    public function update(Request $request, Student $student)
    {
        $this->requireAdmin();

        $validated = $this->validateStudent($request);

        if ($request->hasFile('photo')) {
            if ($student->photo_path) {
                Storage::disk('public')->delete($student->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store("schools/{$this->schoolId()}/students", 'public');
        }

        $student->update($validated);

        return redirect()->route('school.students.show', $student)->with('success', 'Student updated.');
    }

    /**
     * Active classes for dropdowns (auto-scoped to the current school).
     */
    private function activeClasses()
    {
        return SchoolClass::where('is_active', true)->orderBy('grade')->get();
    }

    private function validateStudent(Request $request): array
    {
        return $request->validate([
            'first_name'      => 'required|string|max:100',
            'last_name'       => 'required|string|max:100',
            'first_name_am'   => 'nullable|string|max:100',
            'last_name_am'    => 'nullable|string|max:100',
            'gender'          => 'required|in:male,female',
            'date_of_birth'   => 'nullable|date|before:today',
            'class_id'        => 'nullable|exists:classes,id',
            'blood_type'      => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'medical_notes'   => 'nullable|string|max:500',
            'enrollment_date' => 'nullable|date',
            'status'          => ['nullable', 'in:' . implode(',', self::STATUSES)],
            'join_date'       => 'nullable|date',
            'end_date'        => 'nullable|date|after_or_equal:join_date',
            'photo'           => 'nullable|image|max:2048',
        ]);
    }

    /**
     * Generate a globally-unique student UID.
     *
     * The `uid` column is unique across ALL schools, so the existence check
     * must bypass the per-school global scope — otherwise a UID already used
     * by another school would pass and then fail on insert.
     */
    private function generateUid(): string
    {
        do {
            $uid = strtoupper(Str::random(8));
        } while (Student::withoutGlobalScope('school')->where('uid', $uid)->exists());

        return $uid;
    }
}
