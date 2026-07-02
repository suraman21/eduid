<?php

namespace App\Http\Controllers\School;

use App\Models\AttendanceSession;
use App\Models\CardOrder;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TimetableEntry;
use App\Models\User;
use Inertia\Inertia;

class DashboardController extends BaseSchoolController
{
    public function index()
    {
        // Teachers get their own dashboard — no school-admin widgets
        // (card orders, whole-school registry) leak into their view.
        if (auth()->user()->isTeacher()) {
            return $this->teacherDashboard();
        }

        return $this->adminDashboard();
    }

    // ── School admin / staff dashboard ──────────────────────────

    private function adminDashboard()
    {
        $school = $this->currentSchool();

        $stats = [
            'total_students'  => Student::count(),
            'active_students' => Student::where('status', 'active')->count(),
            'total_staff'     => User::whereIn('role', ['school_admin', 'teacher', 'staff'])->count(),
            'total_classes'   => $school->classes()->count(),
            'pending_orders'  => CardOrder::whereIn('status', CardOrder::OPEN_STATUSES)->count(),
            'today_sessions'  => AttendanceSession::whereDate('session_date', today())->count(),
        ];

        $recentStudents = Student::latest()->take(5)->get()->map(fn ($s) => [
            'id'              => $s->id,
            'full_name'       => $s->full_name,
            'uid'             => $s->uid,
            'photo_url'       => $s->photo_url,
            'initials'        => collect(explode(' ', $s->full_name))->map(fn ($w) => strtoupper($w[0] ?? ''))->take(2)->implode(''),
            'created_at_human' => $s->created_at->diffForHumans(),
        ]);

        // Recent card orders so a school can see fulfilment status at a glance.
        $recentOrders = CardOrder::latest()->take(5)->get()->map(fn ($o) => [
            'id'            => $o->id,
            'order_code'    => $o->order_code,
            'student_count' => $o->student_count,
            'status'        => $o->status,
            'created_at'    => $o->created_at->format('M d, Y'),
        ]);

        return Inertia::render('School/Dashboard', [
            'stats'           => $stats,
            'recent_orders'   => $recentOrders,
            'school'          => [
                'name'                  => $school->name,
                'academic_year'         => $school->academic_year,
                'plan_tier'             => $school->plan_tier,
                'calendar_system'       => $school->calendar_system,
                'subscription_status'   => $school->subscription_status,
                'subscription_expires_at' => $school->subscription_expires_at?->toISOString(),
                'max_students'          => $school->max_students,
            ],
            'recent_students' => $recentStudents,
        ]);
    }

    // ── Teacher dashboard ────────────────────────────────────────

    private function teacherDashboard()
    {
        $user   = auth()->user();
        $school = $this->currentSchool();

        $classes = $user->assignedClasses()
            ->where('is_active', true)
            ->orderBy('level_order')->orderBy('grade')->orderBy('section')
            ->get();

        $classIds = $classes->pluck('id');

        $homeroomIds = SchoolClass::where('homeroom_teacher_id', $user->id)
            ->pluck('id')->flip();

        $subjectsByClass = ClassSubject::where('teacher_id', $user->id)
            ->whereIn('class_id', $classIds)
            ->with('subject:id,name')
            ->get()
            ->groupBy('class_id');

        // Attendance "taken today" per class (any teacher's session counts —
        // the point is whether the class is covered, not who covered it).
        $takenToday = AttendanceSession::whereDate('session_date', today())
            ->whereIn('class_id', $classIds)
            ->pluck('class_id')->flip();

        $studentCounts = Student::whereIn('class_id', $classIds)
            ->where('status', 'active')
            ->selectRaw('class_id, COUNT(*) as c')
            ->groupBy('class_id')
            ->pluck('c', 'class_id');

        // Today's periods for this teacher (empty when timetable isn't set up).
        $todayEntries = TimetableEntry::where('teacher_id', $user->id)
            ->where('day_of_week', now()->dayOfWeekIso)
            ->with(['period:id,label,start_time,end_time', 'schoolClass:id,grade,section', 'subject:id,name'])
            ->get()
            ->sortBy(fn ($e) => $e->period?->start_time)
            ->values();

        $myClasses = $classes->map(fn ($c) => [
            'id'            => $c->id,
            'name'          => $c->display_name,
            'student_count' => (int) ($studentCounts[$c->id] ?? 0),
            'is_homeroom'   => $homeroomIds->has($c->id),
            'subjects'      => ($subjectsByClass[$c->id] ?? collect())
                ->map(fn ($cs) => $cs->subject?->name)->filter()->values(),
            'attendance_taken_today' => $takenToday->has($c->id),
        ]);

        return Inertia::render('School/TeacherDashboard', [
            'school' => [
                'name'            => $school->name,
                'academic_year'   => $school->academic_year,
                'calendar_system' => $school->calendar_system,
            ],
            'my_classes' => $myClasses,
            'today_periods' => $todayEntries->map(fn ($e) => [
                'label'      => $e->period?->label,
                'start_time' => $e->period ? substr($e->period->start_time, 0, 5) : null,
                'end_time'   => $e->period ? substr($e->period->end_time, 0, 5) : null,
                'class_name' => $e->schoolClass ? "{$e->schoolClass->grade} - {$e->schoolClass->section}" : null,
                'subject'    => $e->subject?->name,
                'room'       => $e->room,
            ]),
            'stats' => [
                'classes'          => $classes->count(),
                'students'         => (int) $studentCounts->sum(),
                'taken_today'      => $takenToday->count(),
                'homeroom_classes' => $homeroomIds->count(),
            ],
        ]);
    }
}
