<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Inertia\Inertia;

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

        $leads    = $query->latest()->paginate(25)->withQueryString();
        $newCount = Lead::where('status', 'new')->count();

        return Inertia::render('Admin/Leads/Index', [
            'leads'     => $leads,
            'new_count' => $newCount,
            'filters'   => $request->only('search', 'status'),
        ]);
    }

    public function show(Lead $lead)
    {
        return Inertia::render('Admin/Leads/Show', [
            'lead' => [
                'id'           => $lead->id,
                'name'         => $lead->name,
                'school_name'  => $lead->school_name,
                'phone'        => $lead->phone,
                'email'        => $lead->email,
                'message'      => $lead->message,
                'status'       => $lead->status,
                'admin_notes'  => $lead->admin_notes,
                'created_at'   => $lead->created_at->toISOString(),
                'contacted_at' => $lead->contacted_at?->toISOString(),
            ],
        ]);
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
