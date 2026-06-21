<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\CardOrder;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_schools'   => School::count(),
            'active_schools'  => School::where('is_active', true)->count(),
            'total_students'  => Student::count(),
            'total_teachers'  => User::where('role', 'teacher')->count(),
            'total_staff'     => User::whereIn('role', ['teacher', 'staff', 'school_admin'])->count(),
            'pending_leads'   => Lead::where('status', 'new')->count(),
            'total_revenue'   => Payment::where('status', 'paid')->sum('amount_etb'),
            'pending_orders'  => CardOrder::whereIn('status', ['pending', 'processing'])->count(),
        ];

        $recentSchools = School::latest()->take(5)->get();
        $recentLeads = Lead::latest()->take(5)->get();

        $schoolsByPlan = School::select('plan_tier', DB::raw('count(*) as count'))
            ->groupBy('plan_tier')
            ->pluck('count', 'plan_tier')
            ->toArray();

        return view('admin.dashboard', compact('stats', 'recentSchools', 'recentLeads', 'schoolsByPlan'));
    }
}
