<?php

namespace App\Http\Controllers\School;

use App\Models\Student;
use App\Models\User;
use App\Models\AttendanceSession;
use App\Models\CardOrder;
use Inertia\Inertia;

class DashboardController extends BaseSchoolController
{
    public function index()
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
}
