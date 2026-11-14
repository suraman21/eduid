<?php

namespace App\Services;

use App\Models\AdmissionApplication;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentProfile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admissions pipeline + the conversion into a real student.
 *
 * Conversion is the interesting part: it creates the Student, issues their QR
 * token and opens their first enrolment in one transaction, then writes the
 * student id back onto the application so the record of where they came from
 * is never lost.
 */
class AdmissionService
{
    public function __construct(
        private EnrollmentService $enrollments,
        private QrTokenService $qr,
    ) {
    }

    /** Next reference for a school, e.g. "APP-2026-0007". */
    public function nextReference(School $school): string
    {
        $year = substr((string) $school->academic_year, 0, 4) ?: (string) now()->year;
        $prefix = "APP-{$year}-";

        $last = AdmissionApplication::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('reference', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('reference');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Turn an accepted application into a student.
     *
     * @param  int|null  $classId  overrides the class on the application
     */
    public function convert(AdmissionApplication $application, ?int $classId = null, ?string $note = null): Student
    {
        if ($application->student_id) {
            throw ValidationException::withMessages([
                'application' => 'This application has already been converted into a student.',
            ]);
        }

        if ($application->status === 'rejected') {
            throw ValidationException::withMessages([
                'application' => 'A rejected application cannot be enrolled. Re-open it first.',
            ]);
        }

        $school = School::findOrFail($application->school_id);
        $targetClassId = $classId ?? $application->class_id;

        $cohort = $targetClassId ? SchoolClass::find($targetClassId) : null;

        if ($targetClassId && !$cohort) {
            throw ValidationException::withMessages([
                'class_id' => 'That class no longer exists.',
            ]);
        }

        return DB::transaction(function () use ($application, $school, $cohort, $note) {
            $student = $this->createStudent($application, $school);

            // QR token for the current academic year — same pipeline as a
            // student created from the registry screen.
            $this->qr->generate($student, $school);

            // First enrolment, through the normal lifecycle path so the
            // timeline starts correctly.
            $this->enrollments->enroll($student, [
                'class_id'      => $cohort?->id,
                'programme_id'  => $application->programme_id ?? $cohort?->programme_id,
                'academic_year' => $application->academic_year ?? $cohort?->academic_year,
                'level_number'  => $cohort?->level_number,
                'enrolled_on'   => now()->toDateString(),
                'notes'         => 'Enrolled from admission ' . $application->reference,
            ]);

            // Carry the background across into the extended profile so the
            // registrar does not have to retype it.
            StudentProfile::updateOrCreate(
                ['student_id' => $student->id],
                [
                    'school_id'      => $student->school_id,
                    'address_line'   => $application->address_line,
                    'phone'          => $application->phone,
                    'email'          => $application->email,
                    'previous_school' => $application->previous_school,
                    'previous_grade'  => $application->previous_grade,
                    'admission_source' => $application->source,
                ],
            );

            $application->update([
                'status'       => 'converted',
                'student_id'   => $student->id,
                'converted_at' => now(),
                'decided_at'   => $application->decided_at ?? now(),
                'decision_note' => $note ?? $application->decision_note,
                'handled_by'   => $application->handled_by ?? auth()->id(),
            ]);

            return $student;
        });
    }

    /** Create the student row, retrying on a UID collision. */
    private function createStudent(AdmissionApplication $application, School $school): Student
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(function () use ($application, $school) {
                    return Student::create([
                        'school_id'       => $application->school_id,
                        'uid'             => $this->generateUid($school),
                        'first_name'      => $application->first_name,
                        'last_name'       => $application->last_name,
                        'first_name_am'   => $application->first_name_am,
                        'last_name_am'    => $application->last_name_am,
                        'gender'          => $application->gender ?? 'male',
                        'date_of_birth'   => $application->date_of_birth,
                        'enrollment_date' => now()->toDateString(),
                        'join_date'       => now()->toDateString(),
                        'status'          => 'active',
                        'is_active'       => true,
                    ]);
                });
            } catch (QueryException $e) {
                $duplicateUid = $e->getCode() === '23000' && str_contains($e->getMessage(), 'uid');

                if ($duplicateUid && $attempt < 4) {
                    continue;
                }

                throw $e;
            }
        }

        throw ValidationException::withMessages([
            'uid' => 'Could not allocate a unique student ID. Please try again.',
        ]);
    }

    private function generateUid(School $school): string
    {
        $prefix = strtoupper($school->code) . '-';

        do {
            $uid = $prefix . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        } while (Student::withoutGlobalScope('school')->where('uid', $uid)->exists());

        return $uid;
    }
}
