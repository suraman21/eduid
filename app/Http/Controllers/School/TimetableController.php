<?php

namespace App\Http\Controllers\School;

use App\Models\AuditLog;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Timetable builder — periods (the school's daily bell schedule) plus a
 * weekly subject/teacher/room grid per class. Everything the teacher
 * schedule views (web dashboard, mobile app) render comes from here.
 *
 * Admin-only ring + 'feature:timetable' gate are applied at the route level.
 */
class TimetableController extends BaseSchoolController
{
    public function index(Request $request)
    {
        $classes = SchoolClass::where('is_active', true)
            ->orderBy('level_order')->orderBy('grade')->orderBy('section')
            ->get();

        $classId = (int) ($request->get('class_id') ?: ($classes->first()?->id ?? 0));

        $periods = TimetablePeriod::orderBy('sort_order')->get();

        $entries = $classId
            ? TimetableEntry::where('class_id', $classId)
                ->get(['period_id', 'day_of_week', 'subject_id', 'teacher_id', 'room'])
            : collect();

        // Subject → default teacher mapping for the selected class, so the
        // grid can prefill the teacher when a subject is picked.
        $classSubjects = $classId
            ? ClassSubject::where('class_id', $classId)->get(['subject_id', 'teacher_id'])
            : collect();

        return Inertia::render('School/Timetable/Index', [
            'classes' => $classes->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->display_name,
            ])->values(),
            'selected_class_id' => $classId ?: null,
            'periods' => $periods->map(fn ($p) => [
                'id'         => $p->id,
                'label'      => $p->label,
                'start_time' => substr($p->start_time, 0, 5),
                'end_time'   => substr($p->end_time, 0, 5),
                'is_break'   => (bool) $p->is_break,
            ])->values(),
            'entries' => $entries->map(fn ($e) => [
                'period_id'   => $e->period_id,
                'day_of_week' => $e->day_of_week,
                'subject_id'  => $e->subject_id,
                'teacher_id'  => $e->teacher_id,
                'room'        => $e->room,
            ])->values(),
            'subjects' => Subject::where('is_active', true)->orderBy('name')
                ->get(['id', 'name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
            'teachers' => User::where('role', 'teacher')->where('is_active', true)
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'default_teachers' => $classSubjects
                ->filter(fn ($cs) => $cs->teacher_id)
                ->mapWithKeys(fn ($cs) => [$cs->subject_id => $cs->teacher_id]),
        ]);
    }

    // ── Periods (bell schedule) ──────────────────────────────────

    public function storePeriod(Request $request)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $validated = $request->validate([
            'label'      => 'required|string|max:50',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',
            'is_break'   => 'boolean',
        ]);

        $validated['sort_order'] =
            (int) TimetablePeriod::max('sort_order') + 1;
        $validated['school_id'] = $this->schoolId();
        $validated['is_break'] = $request->boolean('is_break');

        TimetablePeriod::create($validated);

        return back()->with('success', 'Period added.');
    }

    public function updatePeriod(Request $request, TimetablePeriod $period)
    {
        $this->requireAdmin();

        $validated = $request->validate([
            'label'      => 'required|string|max:50',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',
            'is_break'   => 'boolean',
        ]);
        $validated['is_break'] = $request->boolean('is_break');

        $period->update($validated);

        return back()->with('success', 'Period updated.');
    }

    public function destroyPeriod(TimetablePeriod $period)
    {
        $this->requireAdmin();

        // Entries cascade via the FK — warn how many lessons go with it.
        $count = $period->entries()->count();
        $period->delete();

        return back()->with('success', $count > 0
            ? "Period removed along with {$count} scheduled lesson(s)."
            : 'Period removed.');
    }

    // ── Grid entries (one class × period × day) ──────────────────

    public function saveEntry(Request $request)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $validated = $request->validate([
            'class_id'    => ['required', Rule::exists('classes', 'id')->where('school_id', $this->schoolId())],
            'period_id'   => ['required', Rule::exists('timetable_periods', 'id')->where('school_id', $this->schoolId())],
            'day_of_week' => 'required|integer|min:1|max:7',
            'subject_id'  => ['nullable', Rule::exists('subjects', 'id')->where('school_id', $this->schoolId())],
            'teacher_id'  => ['nullable', Rule::exists('users', 'id')->where('school_id', $this->schoolId())->where('role', 'teacher')],
            'room'        => 'nullable|string|max:50',
        ]);

        // A teacher cannot be in two classrooms at once: block double-booking
        // for the same period+day in a different class.
        if (! empty($validated['teacher_id'])) {
            $conflict = TimetableEntry::where('period_id', $validated['period_id'])
                ->where('day_of_week', $validated['day_of_week'])
                ->where('teacher_id', $validated['teacher_id'])
                ->where('class_id', '!=', $validated['class_id'])
                ->with('schoolClass:id,grade,section')
                ->first();

            if ($conflict) {
                $teacher = User::find($validated['teacher_id']);
                $where   = $conflict->schoolClass
                    ? "{$conflict->schoolClass->grade} - {$conflict->schoolClass->section}"
                    : 'another class';

                return back()->with('error',
                    "{$teacher?->name} already teaches {$where} at this time.");
            }
        }

        TimetableEntry::updateOrCreate(
            [
                'class_id'    => $validated['class_id'],
                'period_id'   => $validated['period_id'],
                'day_of_week' => $validated['day_of_week'],
            ],
            [
                'school_id'  => $this->schoolId(),
                'subject_id' => $validated['subject_id'] ?? null,
                'teacher_id' => $validated['teacher_id'] ?? null,
                'room'       => $validated['room'] ?? null,
            ]
        );

        AuditLog::log('timetable.entry_saved', 'timetable_entries', null, null, $validated);

        return back()->with('success', 'Lesson saved.');
    }

    public function clearEntry(Request $request)
    {
        $this->requireAdmin();

        $validated = $request->validate([
            'class_id'    => ['required', Rule::exists('classes', 'id')->where('school_id', $this->schoolId())],
            'period_id'   => 'required|integer',
            'day_of_week' => 'required|integer|min:1|max:7',
        ]);

        TimetableEntry::where('class_id', $validated['class_id'])
            ->where('period_id', $validated['period_id'])
            ->where('day_of_week', $validated['day_of_week'])
            ->delete();

        return back()->with('success', 'Lesson cleared.');
    }
}
