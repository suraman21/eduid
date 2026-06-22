<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $query = Lead::query();

        if ($search = $request->get('search')) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('school_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"));
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $leads   = $query->latest()->paginate(25);
        $newCount = Lead::where('status', 'new')->count();

        return view('admin.leads.index', compact('leads', 'newCount'));
    }

    public function show(Lead $lead)
    {
        return view('admin.leads.show', compact('lead'));
    }

    public function update(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'status'      => 'required|in:new,contacted,demo_scheduled,converted,lost',
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        if ($lead->status === 'new' && $validated['status'] !== 'new') {
            $validated['contacted_at'] = now();
        }

        $lead->update($validated);

        return back()->with('success', 'Lead updated.');
    }
}
