<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\School;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with(['school', 'user'])->latest('created_at');

        if ($schoolId = $request->get('school_id')) {
            $query->where('school_id', $schoolId);
        }

        if ($action = $request->get('action')) {
            $query->where('action', $action);
        }

        if ($from = $request->get('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->get('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs    = $query->paginate(50);
        $schools = School::orderBy('name')->pluck('name', 'id');
        $actions = AuditLog::distinct()->orderBy('action')->pluck('action');

        return view('admin.audit_log.index', compact('logs', 'schools', 'actions'));
    }
}
