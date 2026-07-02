<?php

namespace App\Services;

use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * One writer for everything a teaching assignment touches, so the four
 * stores can never drift apart:
 *
 *   class_subjects.teacher_id      → who teaches 1A English (grade entry rights)
 *   classes.homeroom_teacher_id    → who is homeroom of 2C
 *   teacher_classes                → attendance rights (union of both above)
 *   users.is_homeroom              → derived convenience flag
 *
 * Semantics: a class×subject slot and a class homeroom each hold ONE teacher —
 * assigning a slot to this teacher silently takes it over from the previous
 * holder (the UI shows the current holder so the admin knows).
 */
class TeacherAssignmentService
{
    /**
     * Replace the teacher's assignments with the given set.
     *
     * @param array<int, array{class_id: int, subject_id: int|null}> $assignments
     *        subject_id null = "attendance only" for that class (no subject)
     * @param int[] $homeroomClassIds
     */
    public function sync(User $teacher, array $assignments, array $homeroomClassIds): void
    {
        $classIds = collect($assignments)->pluck('class_id')
            ->merge($homeroomClassIds)
            ->unique()->values();

        $subjectIds = collect($assignments)->pluck('subject_id')->filter()->unique()->values();

        // Every referenced class/subject must belong to the teacher's school.
        // Models are tenant-scoped for school users, but this also runs from
        // super-admin/CLI contexts, so check explicitly against the school.
        $validClasses = SchoolClass::withoutGlobalScope('school')
            ->where('school_id', $teacher->school_id)
            ->whereIn('id', $classIds)->pluck('id');
        if ($validClasses->count() !== $classIds->count()) {
            throw ValidationException::withMessages([
                'assignments' => 'One or more selected classes do not belong to this school.',
            ]);
        }

        $validSubjects = Subject::withoutGlobalScope('school')
            ->where('school_id', $teacher->school_id)
            ->whereIn('id', $subjectIds)->pluck('id');
        if ($validSubjects->count() !== $subjectIds->count()) {
            throw ValidationException::withMessages([
                'assignments' => 'One or more selected subjects do not belong to this school.',
            ]);
        }

        DB::transaction(function () use ($teacher, $assignments, $homeroomClassIds, $classIds) {
            $pairs = collect($assignments)
                ->filter(fn ($a) => ! empty($a['subject_id']))
                ->map(fn ($a) => ['class_id' => (int) $a['class_id'], 'subject_id' => (int) $a['subject_id']])
                ->unique(fn ($a) => $a['class_id'] . ':' . $a['subject_id'])
                ->values();

            // 1. class_subjects — claim each pair (creates the mapping row if the
            //    school hasn't set it up yet), then release pairs no longer held.
            foreach ($pairs as $pair) {
                ClassSubject::withoutGlobalScope('school')->updateOrCreate(
                    [
                        'class_id'   => $pair['class_id'],
                        'subject_id' => $pair['subject_id'],
                    ],
                    [
                        'school_id'  => $teacher->school_id,
                        'teacher_id' => $teacher->id,
                    ]
                );
            }

            ClassSubject::withoutGlobalScope('school')
                ->where('school_id', $teacher->school_id)
                ->where('teacher_id', $teacher->id)
                ->where(function ($q) use ($pairs) {
                    foreach ($pairs as $pair) {
                        $q->whereNot(fn ($qq) => $qq
                            ->where('class_id', $pair['class_id'])
                            ->where('subject_id', $pair['subject_id']));
                    }
                })
                ->update(['teacher_id' => null]);

            // 2. Homeroom — claim listed classes, release the rest.
            SchoolClass::withoutGlobalScope('school')
                ->where('school_id', $teacher->school_id)
                ->whereIn('id', $homeroomClassIds)
                ->update(['homeroom_teacher_id' => $teacher->id]);

            SchoolClass::withoutGlobalScope('school')
                ->where('school_id', $teacher->school_id)
                ->where('homeroom_teacher_id', $teacher->id)
                ->whereNotIn('id', $homeroomClassIds)
                ->update(['homeroom_teacher_id' => null]);

            // 3. Attendance rights = every class they teach or homeroom.
            $teacher->assignedClasses()->sync($classIds->all());

            // 4. Derived flag (legacy consumers read users.is_homeroom).
            $teacher->forceFill(['is_homeroom' => ! empty($homeroomClassIds)])->save();
        });
    }

    /**
     * Strip every assignment (role changed away from teacher, or deactivated).
     */
    public function clear(User $teacher): void
    {
        $this->sync($teacher, [], []);
    }

    /**
     * Current assignments in the shape the staff form edits.
     *
     * @return array{assignments: array, homeroom_class_ids: int[]}
     */
    public function current(User $teacher): array
    {
        $subjectRows = ClassSubject::withoutGlobalScope('school')
            ->where('school_id', $teacher->school_id)
            ->where('teacher_id', $teacher->id)
            ->get(['class_id', 'subject_id']);

        $homeroomIds = SchoolClass::withoutGlobalScope('school')
            ->where('school_id', $teacher->school_id)
            ->where('homeroom_teacher_id', $teacher->id)
            ->pluck('id');

        // Attendance-only classes: in teacher_classes but carrying no subject
        // and not homeroom (kept editable so nothing becomes invisible).
        $attendanceOnly = $teacher->assignedClasses()->pluck('classes.id')
            ->diff($subjectRows->pluck('class_id'))
            ->diff($homeroomIds);

        return [
            'assignments' => $subjectRows
                ->map(fn ($r) => ['class_id' => $r->class_id, 'subject_id' => $r->subject_id])
                ->concat($attendanceOnly->map(fn ($id) => ['class_id' => $id, 'subject_id' => null]))
                ->values()
                ->all(),
            'homeroom_class_ids' => $homeroomIds->all(),
        ];
    }
}
