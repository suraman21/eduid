<?php

namespace App\Http\Controllers\School;

use App\Models\LifecycleEvent;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\StudentProfile;
use App\Services\EnrollmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Everything that changes where a student stands: enrolment, promotion,
 * repetition, transfer, withdrawal, graduation, reinstatement — plus the
 * document vault that proves it happened.
 *
 * Writes are admin-only via requireAdmin(); reads are tenant-scoped by the
 * BelongsToSchool global scope on every model touched here.
 */
class StudentLifecycleController extends BaseSchoolController
{
    public function __construct(private EnrollmentService $enrollments)
    {
    }

    // ── Lifecycle transitions ───────────────────────────────────

    /** Enrol a student who currently has no open enrolment. */
    public function enroll(Request $request, Student $student)
    {
        $this->requireAdmin();

        $data = $request->validate([
            'class_id'      => ['nullable', 'integer', Rule::exists('classes', 'id')
                ->where('school_id', $this->currentSchool()->id)],
            'programme_id'  => ['nullable', 'integer', Rule::exists('programmes', 'id')
                ->where('school_id', $this->currentSchool()->id)],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'term_label'    => ['nullable', 'string', 'max:50'],
            'level_number'  => ['nullable', 'integer', 'min:0', 'max:20'],
            'enrolled_on'   => ['nullable', 'date'],
            'notes'         => ['nullable', 'string', 'max:500'],
        ]);

        $this->enrollments->enroll($student, $data);

        return back()->with('success', 'Student enrolled.');
    }

    /** Promote into another class. */
    public function promote(Request $request, Student $student)
    {
        $this->requireAdmin();

        $data = $request->validate([
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')
                ->where('school_id', $this->currentSchool()->id)],
            'note'     => ['nullable', 'string', 'max:500'],
        ]);

        $target = SchoolClass::findOrFail($data['class_id']);
        $this->enrollments->promote($student, $target, $data['note'] ?? null);

        return back()->with('success', "Promoted to {$target->lifecycle_label}.");
    }

    /** Repeat the year. */
    public function repeat(Request $request, Student $student)
    {
        $this->requireAdmin();

        $data = $request->validate([
            'class_id' => ['nullable', 'integer', Rule::exists('classes', 'id')
                ->where('school_id', $this->currentSchool()->id)],
            'note'     => ['nullable', 'string', 'max:500'],
        ]);

        $target = !empty($data['class_id']) ? SchoolClass::find($data['class_id']) : null;
        $this->enrollments->repeat($student, $target, $data['note'] ?? null);

        return back()->with('success', 'Recorded as repeating.');
    }

    /** Move class within the same year. */
    public function changeClass(Request $request, Student $student)
    {
        $this->requireAdmin();

        $data = $request->validate([
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')
                ->where('school_id', $this->currentSchool()->id)],
            'note'     => ['nullable', 'string', 'max:500'],
        ]);

        $target = SchoolClass::findOrFail($data['class_id']);
        $this->enrollments->changeClass($student, $target, $data['note'] ?? null);

        return back()->with('success', "Moved to {$target->lifecycle_label}.");
    }

    public function transfer(Request $request, Student $student)
    {
        $this->requireAdmin();

        $data = $request->validate([
            'destination_school' => ['required', 'string', 'max:150'],
            'exit_reason'        => ['nullable', 'string', 'max:40'],
            'note'               => ['nullable', 'string', 'max:500'],
            'date'               => ['nullable', 'date'],
        ]);

        $this->enrollments->transferOut(
            $student,
            $data['destination_school'],
            $data['exit_reason'] ?? 'transferred_out',
            $data['note'] ?? null,
            !empty($data['date']) ? Carbon::parse($data['date']) : null,
        );

        return back()->with('success', 'Transfer recorded.');
    }

    public function withdraw(Request $request, Student $student)
    {
        $this->requireAdmin();

        $data = $request->validate([
            'exit_reason' => ['nullable', 'string', 'max:40'],
            'note'        => ['nullable', 'string', 'max:500'],
            'date'        => ['nullable', 'date'],
        ]);

        $this->enrollments->withdraw(
            $student,
            $data['exit_reason'] ?? 'other',
            $data['note'] ?? null,
            !empty($data['date']) ? Carbon::parse($data['date']) : null,
        );

        return back()->with('success', 'Withdrawal recorded.');
    }

    public function graduate(Request $request, Student $student)
    {
        $this->requireAdmin();

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
            'date' => ['nullable', 'date'],
        ]);

        $this->enrollments->graduate(
            $student,
            $data['note'] ?? null,
            !empty($data['date']) ? Carbon::parse($data['date']) : null,
        );

        return back()->with('success', 'Student marked as graduated.');
    }

    public function reinstate(Request $request, Student $student)
    {
        $this->requireAdmin();

        $data = $request->validate([
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')
                ->where('school_id', $this->currentSchool()->id)],
            'note'     => ['nullable', 'string', 'max:500'],
        ]);

        $target = SchoolClass::findOrFail($data['class_id']);
        $this->enrollments->reinstate($student, $target, $data['note'] ?? null);

        return back()->with('success', "Reinstated into {$target->lifecycle_label}.");
    }

    // ── Extended profile ────────────────────────────────────────

    public function updateProfile(Request $request, Student $student)
    {
        $this->requireAdmin();

        $data = $request->validate([
            'national_id'              => ['nullable', 'string', 'max:40'],
            'birth_place'              => ['nullable', 'string', 'max:120'],
            'nationality'              => ['nullable', 'string', 'max:60'],
            'mother_tongue'            => ['nullable', 'string', 'max:60'],
            'religion'                 => ['nullable', 'string', 'max:60'],
            'region'                   => ['nullable', 'string', 'max:80'],
            'zone'                     => ['nullable', 'string', 'max:80'],
            'woreda'                   => ['nullable', 'string', 'max:80'],
            'kebele'                   => ['nullable', 'string', 'max:80'],
            'house_number'             => ['nullable', 'string', 'max:40'],
            'address_line'             => ['nullable', 'string', 'max:255'],
            'phone'                    => ['nullable', 'string', 'max:30'],
            'email'                    => ['nullable', 'email', 'max:120'],
            'emergency_contact_name'   => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone'  => ['nullable', 'string', 'max:30'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:40'],
            'previous_school'          => ['nullable', 'string', 'max:150'],
            'previous_school_year'     => ['nullable', 'string', 'max:20'],
            'previous_grade'           => ['nullable', 'string', 'max:40'],
            'admission_source'         => ['nullable', 'string', 'max:80'],
            'allergies'                => ['nullable', 'string', 'max:255'],
            'chronic_conditions'       => ['nullable', 'string', 'max:255'],
            'insurance_provider'       => ['nullable', 'string', 'max:120'],
            'insurance_number'         => ['nullable', 'string', 'max:60'],
            'special_needs'            => ['nullable', 'string', 'max:2000'],
        ]);

        StudentProfile::updateOrCreate(
            ['student_id' => $student->id],
            array_merge($data, ['school_id' => $student->school_id]),
        );

        return back()->with('success', 'Student details saved.');
    }

    // ── Documents ───────────────────────────────────────────────

    public function storeDocument(Request $request, Student $student)
    {
        $this->requireAdmin();

        $data = $request->validate([
            'type'        => ['required', Rule::in(array_keys(StudentDocument::TYPES))],
            'title'       => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'issued_on'   => ['nullable', 'date'],
            'expires_on'  => ['nullable', 'date', 'after_or_equal:issued_on'],
            'file'        => ['required', 'file', 'max:10240', // 10 MB
                'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $file = $request->file('file');
        $path = $file->store(
            "students/{$student->school_id}/{$student->id}/documents",
            'local',
        );

        $document = StudentDocument::create([
            'school_id'     => $student->school_id,
            'student_id'    => $student->id,
            'type'          => $data['type'],
            'title'         => $data['title'],
            'description'   => $data['description'] ?? null,
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'file_size'     => $file->getSize(),
            'issued_on'     => $data['issued_on'] ?? null,
            'expires_on'    => $data['expires_on'] ?? null,
            'uploaded_by'   => auth()->id(),
        ]);

        LifecycleEvent::create([
            'school_id'     => $student->school_id,
            'student_id'    => $student->id,
            'event'         => 'document_added',
            'occurred_on'   => now()->toDateString(),
            'notes'         => "{$document->typeLabel()}: {$document->title}",
            'recorded_by'   => auth()->id(),
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    /** Stream a document through an authenticated, tenant-scoped route. */
    public function showDocument(Student $student, StudentDocument $document)
    {
        abort_unless($document->student_id === $student->id, 404);

        if (!Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'File missing.');
        }

        return Storage::disk('local')->response(
            $document->file_path,
            $document->original_name ?: $document->title,
        );
    }

    public function downloadDocument(Student $student, StudentDocument $document)
    {
        abort_unless($document->student_id === $student->id, 404);

        if (!Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'File missing.');
        }

        return Storage::disk('local')->download(
            $document->file_path,
            $document->original_name ?: $document->title,
        );
    }

    public function destroyDocument(Student $student, StudentDocument $document)
    {
        $this->requireAdmin();
        abort_unless($document->student_id === $student->id, 404);

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Document deleted.');
    }

    public function verifyDocument(Student $student, StudentDocument $document)
    {
        $this->requireAdmin();
        abort_unless($document->student_id === $student->id, 404);

        $document->update([
            'is_verified' => !$document->is_verified,
            'verified_by' => $document->is_verified ? null : auth()->id(),
            'verified_at' => $document->is_verified ? null : now(),
        ]);

        return back()->with('success', $document->is_verified ? 'Document verified.' : 'Verification removed.');
    }
}
