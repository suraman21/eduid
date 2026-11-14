<?php

namespace App\Services;

use App\Models\LifecycleEvent;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Everything that moves a student through their time at the school.
 *
 * Design rule: an enrolment row is never edited to reflect a new state.
 * The current row is *closed* with an outcome, and a new row is *opened*.
 * That is what makes history survive — the single most important difference
 * between this and the old "overwrite students.class_id" behaviour.
 *
 * The service also mirrors the current state back onto `students` (class_id,
 * status, is_active) so attendance, grades, timetable, fees, ID cards and the
 * Flutter API keep working exactly as before.
 */
class EnrollmentService
{
    // ── Entry ───────────────────────────────────────────────────

    /**
     * Enrol a student who has no current enrolment (new admission, or a
     * re-admission after leaving).
     */
    public function enroll(Student $student, array $data): StudentEnrollment
    {
        return DB::transaction(function () use ($student, $data) {
            $open = $this->currentFor($student);

            if ($open) {
                throw ValidationException::withMessages([
                    'student' => 'This student already has an open enrolment. '
                        . 'Use "Change class" to move them, or close the current enrolment first.',
                ]);
            }

            $cohort = !empty($data['class_id'])
                ? SchoolClass::find($data['class_id'])
                : null;

            $enrollment = $this->openNew($student, [
                'class_id'      => $cohort?->id,
                'programme_id'  => $data['programme_id'] ?? $cohort?->programme_id,
                'academic_year' => $data['academic_year'] ?? $cohort?->academic_year,
                'term_label'    => $data['term_label'] ?? null,
                'level_number'  => $data['level_number'] ?? $cohort?->level_number,
                'enrolled_on'   => $data['enrolled_on'] ?? now()->toDateString(),
            ]);

            $this->log($student, 'enrolled', [
                'enrollment_id' => $enrollment->id,
                'occurred_on'   => $enrollment->enrolled_on,
                'to_label'      => $enrollment->label(),
                'notes'         => $data['notes'] ?? null,
            ]);

            $this->syncStudent($student, $enrollment);
            $this->ensureProfile($student);

            return $enrollment->fresh();
        });
    }

    /**
     * Move a student to a different class within the same academic year.
     * The enrolment row is replaced (same year), but the timeline keeps both.
     */
    public function changeClass(Student $student, SchoolClass $target, ?string $reason = null): StudentEnrollment
    {
        return DB::transaction(function () use ($student, $target, $reason) {
            $current = $this->requireCurrent($student);
            $fromLabel = $current->label();

            $current->update([
                'class_id'     => $target->id,
                'programme_id' => $target->programme_id ?? $current->programme_id,
                'level_number' => $target->level_number ?? $current->level_number,
            ]);

            $this->log($student, 'class_changed', [
                'enrollment_id' => $current->id,
                'occurred_on'   => now()->toDateString(),
                'from_label'    => $fromLabel,
                'to_label'      => $current->fresh()->label(),
                'notes'         => $reason,
            ]);

            $this->syncStudent($student, $current->fresh());

            return $current->fresh();
        });
    }

    /**
     * Promote into the next cohort. Closes the current enrolment as
     * 'promoted' and opens a new one.
     */
    public function promote(Student $student, SchoolClass $target, ?string $note = null): StudentEnrollment
    {
        return DB::transaction(function () use ($student, $target, $note) {
            $current = $this->requireCurrent($student);
            $fromLabel = $current->label();

            $this->closeCurrent($current, 'promoted', [
                'ended_on' => now()->toDateString(),
            ]);

            $next = $this->openNew($student, [
                'class_id'      => $target->id,
                'programme_id'  => $target->programme_id ?? $current->programme_id,
                'academic_year' => $target->academic_year,
                'level_number'  => $target->level_number,
                'enrolled_on'   => now()->toDateString(),
            ]);

            $this->log($student, 'promoted', [
                'enrollment_id' => $next->id,
                'occurred_on'   => $next->enrolled_on,
                'from_label'    => $fromLabel,
                'to_label'      => $next->label(),
                'notes'         => $note,
            ]);

            $this->syncStudent($student, $next);

            return $next->fresh();
        });
    }

    /**
     * Repeat: close the current enrolment as 'repeated' and open a new one,
     * normally in the same level — the student stays behind deliberately.
     */
    public function repeat(Student $student, ?SchoolClass $target = null, ?string $note = null): StudentEnrollment
    {
        return DB::transaction(function () use ($student, $target, $note) {
            $current = $this->requireCurrent($student);
            $fromLabel = $current->label();
            $target ??= $current->cohort;

            $this->closeCurrent($current, 'repeated', [
                'ended_on'    => now()->toDateString(),
                'exit_reason' => 'academic',
            ]);

            $next = $this->openNew($student, [
                'class_id'      => $target?->id,
                'programme_id'  => $target?->programme_id ?? $current->programme_id,
                'academic_year' => $target?->academic_year,
                'level_number'  => $target?->level_number ?? $current->level_number,
                'enrolled_on'   => now()->toDateString(),
            ]);

            $this->log($student, 'repeated', [
                'enrollment_id' => $next->id,
                'occurred_on'   => $next->enrolled_on,
                'from_label'    => $fromLabel,
                'to_label'      => $next->label(),
                'notes'         => $note,
            ]);

            $this->syncStudent($student, $next);

            return $next->fresh();
        });
    }

    /**
     * Transfer out to another institution.
     */
    public function transferOut(
        Student $student,
        string $destinationSchool,
        ?string $reason = 'transferred_out',
        ?string $note = null,
        ?Carbon $date = null,
    ): StudentEnrollment {
        return DB::transaction(function () use ($student, $destinationSchool, $reason, $note, $date) {
            $current = $this->requireCurrent($student);
            $fromLabel = $current->label();
            $date ??= now();

            $this->closeCurrent($current, 'transferred', [
                'ended_on'           => $date->toDateString(),
                'exit_reason'        => $reason ?: 'transferred_out',
                'exit_note'          => $note,
                'destination_school' => $destinationSchool,
            ]);

            $student->forceFill([
                'status'    => 'transferred',
                'is_active' => false,
                'end_date'  => $date->toDateString(),
            ])->save();

            StudentProfile::updateOrCreate(
                ['student_id' => $student->id],
                [
                    'school_id'            => $student->school_id,
                    'exit_reason_category' => $reason,
                    'exit_note'            => $note,
                    'exit_destination'     => $destinationSchool,
                ],
            );

            $this->log($student, 'transferred_out', [
                'enrollment_id' => $current->id,
                'occurred_on'   => $date,
                'from_label'    => $fromLabel,
                'notes'         => trim("To: {$destinationSchool}. " . ($note ?? '')),
            ]);

            return $current->fresh();
        });
    }

    /**
     * Withdraw (left, not transferring).
     */
    public function withdraw(
        Student $student,
        ?string $reason = 'other',
        ?string $note = null,
        ?Carbon $date = null,
    ): StudentEnrollment {
        return DB::transaction(function () use ($student, $reason, $note, $date) {
            $current = $this->requireCurrent($student);
            $fromLabel = $current->label();
            $date ??= now();

            $this->closeCurrent($current, 'withdrawn', [
                'ended_on'    => $date->toDateString(),
                'exit_reason' => $reason ?: 'other',
                'exit_note'   => $note,
            ]);

            $student->forceFill([
                'status'    => 'withdrawn',
                'is_active' => false,
                'end_date'  => $date->toDateString(),
            ])->save();

            StudentProfile::updateOrCreate(
                ['student_id' => $student->id],
                [
                    'school_id'            => $student->school_id,
                    'exit_reason_category' => $reason,
                    'exit_note'            => $note,
                ],
            );

            $this->log($student, 'withdrawn', [
                'enrollment_id' => $current->id,
                'occurred_on'   => $date,
                'from_label'    => $fromLabel,
                'notes'         => $note,
            ]);

            return $current->fresh();
        });
    }

    /**
     * Graduate / complete the programme.
     */
    public function graduate(Student $student, ?string $note = null, ?Carbon $date = null): StudentEnrollment
    {
        return DB::transaction(function () use ($student, $note, $date) {
            $current = $this->requireCurrent($student);
            $fromLabel = $current->label();
            $date ??= now();

            $this->closeCurrent($current, 'graduated', [
                'ended_on'    => $date->toDateString(),
                'exit_reason' => 'completed_studies',
                'exit_note'   => $note,
            ]);

            $student->forceFill([
                'status'    => 'graduated',
                'is_active' => false,
                'end_date'  => $date->toDateString(),
            ])->save();

            $this->log($student, 'graduated', [
                'enrollment_id' => $current->id,
                'occurred_on'   => $date,
                'from_label'    => $fromLabel,
                'notes'         => $note,
            ]);

            return $current->fresh();
        });
    }

    /**
     * Bring a former student back. Reopens them into a cohort and records it.
     */
    public function reinstate(Student $student, SchoolClass $target, ?string $note = null): StudentEnrollment
    {
        return DB::transaction(function () use ($student, $target, $note) {
            // Close any lingering open row defensively.
            if ($open = $this->currentFor($student)) {
                $this->closeCurrent($open, 'withdrawn', ['ended_on' => now()->toDateString()]);
            }

            $enrollment = $this->openNew($student, [
                'class_id'      => $target->id,
                'programme_id'  => $target->programme_id,
                'academic_year' => $target->academic_year,
                'level_number'  => $target->level_number,
                'enrolled_on'   => now()->toDateString(),
            ]);

            $this->log($student, 'reinstated', [
                'enrollment_id' => $enrollment->id,
                'occurred_on'   => $enrollment->enrolled_on,
                'to_label'      => $enrollment->label(),
                'notes'         => $note,
            ]);

            $this->syncStudent($student, $enrollment);

            return $enrollment->fresh();
        });
    }

    // ── Internals ───────────────────────────────────────────────

    public function currentFor(Student $student): ?StudentEnrollment
    {
        return StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->where('is_current', true)
            ->latest('enrolled_on')
            ->first();
    }

    protected function requireCurrent(Student $student): StudentEnrollment
    {
        $current = $this->currentFor($student);

        if (!$current) {
            throw ValidationException::withMessages([
                'student' => 'This student has no open enrolment. Enrol them first.',
            ]);
        }

        return $current;
    }

    protected function openNew(Student $student, array $data): StudentEnrollment
    {
        return StudentEnrollment::create([
            'school_id'     => $student->school_id,
            'student_id'    => $student->id,
            'class_id'      => $data['class_id'] ?? null,
            'programme_id'  => $data['programme_id'] ?? null,
            'academic_year' => $data['academic_year'] ?? null,
            'term_label'    => $data['term_label'] ?? null,
            'level_number'  => $data['level_number'] ?? null,
            'enrolled_on'   => $data['enrolled_on'] ?? now()->toDateString(),
            'status'        => 'enrolled',
            'is_current'    => true,
        ]);
    }

    protected function closeCurrent(StudentEnrollment $enrollment, string $status, array $overrides = []): void
    {
        $enrollment->update(array_merge([
            'status'     => $status,
            'is_current' => false,
            'ended_on'   => $overrides['ended_on'] ?? now()->toDateString(),
        ], $overrides));
    }

    /**
     * Mirror the live enrolment back onto `students` so every pre-existing
     * feature (attendance rosters, gradebook, fees, ID cards, the mobile API)
     * keeps seeing the truth without a single change on its side.
     */
    protected function syncStudent(Student $student, StudentEnrollment $enrollment): void
    {
        $status = match ($enrollment->status) {
            'repeated' => 'repeating',
            'graduated' => 'graduated',
            'transferred' => 'transferred',
            'withdrawn' => 'withdrawn',
            default => 'active',
        };

        $student->forceFill([
            'class_id'  => $enrollment->class_id,
            'status'    => $status,
            'is_active' => $enrollment->is_current,
        ])->save();
    }

    protected function log(Student $student, string $event, array $data = []): LifecycleEvent
    {
        return LifecycleEvent::create([
            'school_id'     => $student->school_id,
            'student_id'    => $student->id,
            'enrollment_id' => $data['enrollment_id'] ?? null,
            'event'         => $event,
            'occurred_on'   => $data['occurred_on'] ?? now()->toDateString(),
            'from_label'    => $data['from_label'] ?? null,
            'to_label'      => $data['to_label'] ?? null,
            'notes'         => $data['notes'] ?? null,
            'recorded_by'   => auth()->id(),
        ]);
    }

    protected function ensureProfile(Student $student): void
    {
        StudentProfile::firstOrCreate(
            ['student_id' => $student->id],
            ['school_id' => $student->school_id],
        );
    }
}
