<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\MakeupClass;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;

class ScheduleController extends BaseApiController
{
    /**
     * Today's schedule for the authenticated teacher. Returns periods with
     * their entries for the current day-of-week. The app uses this to show
     * "My Schedule Today" and highlight the current period.
     */
    public function today()
    {
        $user = $this->user();
        $dayOfWeek = (int) now()->dayOfWeekIso; // 1=Mon … 7=Sun

        $periods = TimetablePeriod::orderBy('sort_order')->get();

        $entries = TimetableEntry::where('teacher_id', $user->id)
            ->where('day_of_week', $dayOfWeek)
            ->with(['schoolClass', 'subject', 'period'])
            ->get()
            ->keyBy('period_id');

        $schedule = $periods->map(fn ($p) => [
            'period_id'  => $p->id,
            'label'      => $p->label,
            'start_time' => $p->start_time,
            'end_time'   => $p->end_time,
            'is_break'   => $p->is_break,
            'entry'      => $entries->has($p->id) ? [
                'class_name'   => $entries[$p->id]->schoolClass?->display_name,
                'class_id'     => $entries[$p->id]->class_id,
                'subject_name' => $entries[$p->id]->subject?->name,
                'room'         => $entries[$p->id]->room,
            ] : null,
        ]);

        // One-off makeup / extra classes scheduled for THIS date only. Returned
        // as a separate list because they may fall outside the bell periods.
        $makeups = MakeupClass::where('teacher_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->with(['schoolClass', 'subject'])
            ->orderBy('start_time')
            ->get()
            ->map(fn ($m) => [
                'id'           => $m->id,
                'class_name'   => $m->schoolClass?->display_name,
                'class_id'     => $m->class_id,
                'subject_name' => $m->subject?->name,
                'start_time'   => substr($m->start_time, 0, 5),
                'end_time'     => substr($m->end_time, 0, 5),
                'room'         => $m->room,
                'reason'       => $m->reason,
            ]);

        return response()->json([
            'day_of_week'    => $dayOfWeek,
            'date'           => now()->toDateString(),
            'schedule'       => $schedule,
            'makeup_classes' => $makeups,
        ]);
    }

    /**
     * Full weekly schedule for the teacher. Cached by the app for offline.
     */
    public function week()
    {
        $user = $this->user();

        $periods = TimetablePeriod::orderBy('sort_order')->get();

        $entries = TimetableEntry::where('teacher_id', $user->id)
            ->with(['schoolClass', 'subject', 'period'])
            ->get()
            ->groupBy('day_of_week');

        $week = [];
        for ($day = 1; $day <= 7; $day++) {
            $dayEntries = $entries[$day] ?? collect();
            $week[] = [
                'day' => $day,
                'periods' => $periods->map(fn ($p) => [
                    'period_id'  => $p->id,
                    'label'      => $p->label,
                    'start_time' => $p->start_time,
                    'end_time'   => $p->end_time,
                    'is_break'   => $p->is_break,
                    'entry'      => ($e = $dayEntries->firstWhere('period_id', $p->id)) ? [
                        'class_name'   => $e->schoolClass?->display_name,
                        'class_id'     => $e->class_id,
                        'subject_name' => $e->subject?->name,
                        'room'         => $e->room,
                    ] : null,
                ]),
            ];
        }

        return response()->json(['week' => $week]);
    }
}
