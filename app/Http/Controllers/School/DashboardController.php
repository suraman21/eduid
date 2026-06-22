<?php

namespace App\Http\Controllers\School;

use App\Models\Student;
use App\Models\User;
use App\Models\AttendanceSession;
use App\Models\CardOrder;

class DashboardController extends BaseSchoolController
{
    public function index()
    {
        $school = $this->currentSchool();

        // All models below use the BelongsToSchool global scope, so these
        // counts are automatically restricted to the current school.
        $stats = [
            'total_students'  => Student::count(),
            'active_students' => Student::where('status', 'active')->count(),
            'total_staff'     => User::whereIn('role', ['school_admin', 'teacher', 'staff'])->count(),
            'total_classes'   => $school->classes()->count(),
            'pending_orders'  => CardOrder::whereIn('status', CardOrder::OPEN_STATUSES)->count(),
            'today_sessions'  => AttendanceSession::whereDate('session_date', today())->count(),
        ];

        $recentStudents = Student::latest()->take(5)->get();

        return view('school.dashboard', compact('stats', 'school', 'recentStudents'));
    }
}
