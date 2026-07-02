<?php

namespace App\Http\Controllers\School;

use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use App\Models\Student;
use Inertia\Inertia;

/**
 * Analytics dashboard (PRD Phase-2 paid module, 'analytics' feature key).
 *
 * Everything is a 30-day window over attendance + the live enrolment
 * registry. "Attended" counts present + late (the student showed up);
 * absent and excused count against the rate. All queries start from
 * tenant-scoped models, so the join never leaves the school.
 */
class AnalyticsController extends BaseSchoolController
{
    private const WINDOW_DAYS = 30;

    public function index()
    {
        $since = today()->subDays(self::WINDOW_DAYS - 1);

        $joined = fn () => AttendanceSession::query()
            ->whereDate('session_date', '>=', $since)
            ->join('attendance_records', 'attendance_records.session_id', '=', 'attendance_sessions.id');

        // ── Daily attendance rate (chart) ────────────────────────
        $daily = $joined()
            ->selectRaw("DATE(session_date) as d,
                SUM(CASE WHEN attendance_records.status IN ('present','late') THEN 1 ELSE 0 END) as attended,
                SUM(CASE WHEN attendance_records.status = 'absent' THEN 1 ELSE 0 END) as absent,
                COUNT(*) as total")
            ->groupBy('d')->orderBy('d')
            ->get()
            ->map(fn ($r) => [
                'date'     => (string) $r->d,
                'rate'     => $r->total > 0 ? round($r->attended / $r->total * 100, 1) : null,
                'absent'   => (int) $r->absent,
                'total'    => (int) $r->total,
            ]);

        // ── Per-class rates ──────────────────────────────────────
        $classRows = $joined()
            ->selectRaw("attendance_sessions.class_id as cid,
                SUM(CASE WHEN attendance_records.status IN ('present','late') THEN 1 ELSE 0 END) as attended,
                COUNT(*) as total")
            ->groupBy('cid')
            ->get()
            ->keyBy('cid');

        $classes = SchoolClass::whereIn('id', $classRows->keys())->get()->keyBy('id');

        $byClass = $classRows->map(fn ($r) => [
            'class_id' => (int) $r->cid,
            'name'     => $classes[$r->cid]->display_name ?? '?',
            'rate'     => $r->total > 0 ? round($r->attended / $r->total * 100, 1) : null,
            'total'    => (int) $r->total,
        ])->sortBy('rate')->values();

        // ── Absence hotspots (top 10 students) ───────────────────
        $absentRows = $joined()
            ->where('attendance_records.status', 'absent')
            ->selectRaw('attendance_records.student_id as sid, COUNT(*) as c')
            ->groupBy('sid')->orderByDesc('c')->limit(10)
            ->get();

        $students = Student::with('schoolClass:id,grade,section')
            ->whereIn('id', $absentRows->pluck('sid'))->get()->keyBy('id');

        $topAbsent = $absentRows->map(fn ($r) => [
            'student_id' => (int) $r->sid,
            'name'       => $students[$r->sid]->full_name ?? '?',
            'class'      => $students[$r->sid]?->schoolClass
                ? $students[$r->sid]->schoolClass->display_name
                : '—',
            'absences'   => (int) $r->c,
        ])->values();

        // ── Enrolment breakdown ──────────────────────────────────
        $enrolByClass = Student::where('status', 'active')
            ->selectRaw('class_id, COUNT(*) as c')
            ->groupBy('class_id')->pluck('c', 'class_id');

        $enrolClasses = SchoolClass::whereIn('id', $enrolByClass->keys()->filter())
            ->orderBy('level_order')->orderBy('grade')->orderBy('section')->get();

        $genders = Student::where('status', 'active')
            ->selectRaw('gender, COUNT(*) as c')
            ->groupBy('gender')->pluck('c', 'gender');

        // ── Headline numbers ─────────────────────────────────────
        $totals = $joined()
            ->selectRaw("SUM(CASE WHEN attendance_records.status IN ('present','late') THEN 1 ELSE 0 END) as attended,
                COUNT(*) as total")
            ->first();

        return Inertia::render('School/Analytics/Index', [
            'window_days' => self::WINDOW_DAYS,
            'summary' => [
                'attendance_rate' => ($totals?->total ?? 0) > 0
                    ? round($totals->attended / $totals->total * 100, 1)
                    : null,
                'sessions'        => AttendanceSession::whereDate('session_date', '>=', $since)->count(),
                'active_students' => Student::where('status', 'active')->count(),
                'classes_tracked' => $byClass->count(),
            ],
            'daily'      => $daily,
            'by_class'   => $byClass,
            'top_absent' => $topAbsent,
            'enrolment'  => [
                'by_class' => $enrolClasses->map(fn ($c) => [
                    'name'  => $c->display_name,
                    'count' => (int) ($enrolByClass[$c->id] ?? 0),
                ])->values(),
                'unassigned' => (int) Student::where('status', 'active')->whereNull('class_id')->count(),
                'male'       => (int) ($genders['male'] ?? 0),
                'female'     => (int) ($genders['female'] ?? 0),
            ],
        ]);
    }
}
