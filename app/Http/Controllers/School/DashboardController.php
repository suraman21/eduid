<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Models\AttendanceSession;
use App\Models\CardOrder;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $school = Auth::user()->school;
        $schoolId = $school->id;

        $stats = [
            'total_students'  => Student::where('school_id', $schoolId)->count(),
            'active_students' => Student::where('school_id', $schoolId)->where('status', 'active')->count(),
            'total_staff'     => User::where('school_id', $schoolId)->whereIn('role', ['school_admin', 'teacher', 'staff'])->count(),
            'total_classes'   => $school->classes()->count(),
            'pending_orders'  => CardOrder::where('school_id', $schoolId)->whereIn('status', ['pending', 'processing'])->count(),
            'today_sessions'  => AttendanceSession::where('school_id', $schoolId)->whereDate('session_date', today())->count(),
        ];

        $recentStudents = Student::where('school_id', $schoolId)->latest()->take(5)->get();

        return view('school.dashboard', compact('stats', 'school', 'recentStudents'));
    }
}
