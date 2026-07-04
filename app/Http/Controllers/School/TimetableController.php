<?php

namespace App\Http\Controllers\School;

use App\Models\AuditLog;
use App\Models\ClassSubject;
use App\Models\MakeupClass;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\User;
use Illuminate\Support\Carbon;
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
            // The school's chosen week (ISO 1..6/7). The grid renders only these
            // columns, so a Mon–Fri school never sees an empty Saturday.
            'working_days' => $this->currentSchool()->workingDays(),
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
            // Upcoming one-off makeup / extra classes (today onward), so the
            // admin can see and manage them alongside the recurring grid.
            'makeup_classes' => MakeupClass::with(['schoolClass:id,grade,section', 'subject:id,name', 'teacher:id,name'])
                ->whereDate('date', '>=', today())
                ->orderBy('date')->orderBy('start_time')
                ->get()
                ->map(fn ($m) => [
                    'id'         => $m->id,
                    'date'       => $m->date->toDateString(),
                    'start_time' => substr($m->start_time, 0, 5),
                    'end_time'   => substr($m->end_time, 0, 5),
                    'class_id'   => $m->class_id,
                    'class_name' => $m->schoolClass?->display_name,
                    'subject_id' => $m->subject_id,
                    'subject'    => $m->subject?->name,
                    'teacher_id' => $m->teacher_id,
                    'teacher'    => $m->teacher?->name,
                    'room'       => $m->room,
                    'reason'     => $m->reason,
                ])->values(),
        ]);
    }

    // ── Makeup / extra classes (one-off, date-specific) ──────────

    public function storeMakeup(Request $request)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $validated = $request->validate([
            'class_id'   => ['required', Rule::exists('classes', 'id')->where('school_id', $this->schoolId())],
            'date'       => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',
            'subject_id' => ['nullable', Rule::exists('subjects', 'id')->where('school_id', $this->schoolId())],
            'teacher_id' => ['nullable', Rule::exists('users', 'id')->where('school_id', $this->schoolId())->where('role', 'teacher')],
            'room'       => 'nullable|string|max:50',
            'reason'     => 'nullable|string|max:255',
        ]);

        // Don't put a teacher in two places at once on the SAME date: check both
        // other makeup classes and the recurring grid for that weekday.
        if (! empty($validated['teacher_id'])) {
            $conflict = $this->teacherBusyAt(
                (int) $validated['teacher_id'], $validated['date'],
                $validated['start_time'], $validated['end_time']
            );
            if ($conflict) {
                return back()->with('error', $conflict);
            }
        }

        MakeupClass::create($validated + [
            'school_id'  => $this->schoolId(),
            'created_by' => auth()->id(),
        ]);

        AuditLog::log('timetable.makeup_created', 'makeup_classes', null, null, $validated);

        return back()->with('success', 'Makeup class scheduled.');
    }

    public function destroyMakeup(MakeupClass $makeup)
    {
        $this->requireAdmin();

        $makeup->delete();

        return back()->with('success', 'Makeup class removed.');
    }

    /**
     * Is the teacher already committed at [start,end) on this date? Returns a
     * human message if so (recurring lesson or another makeup), else null.
     */
    private function teacherBusyAt(int $teacherId, string $date, string $start, string $end): ?string
    {
        // 1. Another makeup class that overlaps on the same date.
        $overlapMakeup = MakeupClass::where('teacher_id', $teacherId)
            ->whereDate('date', $date)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->exists();
        if ($overlapMakeup) {
            return 'That teacher already has another makeup class overlapping this time.';
        }

        // 2. A recurring lesson whose period overlaps, on that weekday.
        $dow = (int) Carbon::parse($date)->dayOfWeekIso;
        $clash = TimetableEntry::where('teacher_id', $teacherId)
            ->where('day_of_week', $dow)
            ->whereHas('period', fn ($q) => $q
                ->where('start_time', '<', $end)
                ->where('end_time', '>', $start))
            ->exists();

        return $clash ? 'That teacher has a regular lesson at this time on that weekday.' : null;
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

    // ── Working days (which days the school operates) ────────────

    public function updateWorkingDays(Request $request)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $validated = $request->validate([
            'working_days'   => 'required|array|min:1',
            'working_days.*' => 'integer|min:1|max:7',
        ]);

        $days = collect($validated['working_days'])
            ->map(fn ($d) => (int) $d)->unique()->sort()->values()->all();

        $school = $this->currentSchool();
        $school->update(['working_days' => $days]);

        // Any lessons scheduled on a day that is no longer a working day become
        // orphans (teachers would still see them). Remove them so the timetable
        // stays truthful — mirrors how deleting a period drops its lessons.
        $removed = TimetableEntry::whereNotIn('day_of_week', $days)->delete();

        AuditLog::log('timetable.working_days_updated', 'schools', $school->id, null, ['working_days' => $days]);

        return back()->with('success', $removed > 0
            ? "Working days updated. {$removed} lesson(s) on removed days were cleared."
            : 'Working days updated.');
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

        // Never schedule a lesson on a day the school doesn't operate — that
        // would create a ghost entry teachers could see but never teach.
        if (! in_array((int) $validated['day_of_week'], $this->currentSchool()->workingDays(), true)) {
            return back()->with('error', 'That day is not one of the school\'s working days.');
        }

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
