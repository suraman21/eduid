<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;

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

        $logs    = $query->paginate(50)->withQueryString();
        $schools = School::orderBy('name')->get(['id', 'name']);
        $actions = AuditLog::distinct()->orderBy('action')->pluck('action');

        return Inertia::render('Admin/AuditLog/Index', [
            'logs'    => $logs->through(fn ($l) => [
                'id'          => $l->id,
                'action'      => $l->action,
                'school_name' => $l->school?->name,
                'user_name'   => $l->user?->name,
                'target_type' => $l->target_type,
                'target_id'   => $l->target_id,
                'old_values'  => $l->old_values,
                'new_values'  => $l->new_values,
                'created_at'  => $l->created_at->toISOString(),
            ]),
            'schools' => $schools,
            'actions' => $actions,
            'filters' => $request->only('school_id', 'action', 'date_from', 'date_to'),
        ]);
    }
}
