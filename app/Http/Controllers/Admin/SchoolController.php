<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SchoolController extends Controller
{
    public function index(Request $request)
    {
        $schools = School::query()
            ->withCount(['students', 'users'])
            ->latest()
            ->get()
            ->map(fn ($s) => [
                'id'                  => $s->id,
                'name'                => $s->name,
                'code'                => $s->code,
                'email'               => $s->email,
                'plan_tier'           => $s->plan_tier ?? 'starter',
                'students_count'      => $s->students_count,
                'users_count'         => $s->users_count,
                'is_active'           => (bool) $s->is_active,
                'subscription_status' => $s->subscription_status ?? 'active',
            ]);

        return Inertia::render('Admin/Schools/Index', [
            'schools' => $schools,
        ]);
    }

    public function create()
    {
        return view('admin.schools.form', ['school' => new School()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'code'         => 'required|string|max:20|unique:schools,code',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:20',
            'address'      => 'nullable|string|max:500',
            'plan_tier'    => 'required|in:starter,standard,premium',
            'max_students' => 'required|integer|min:1',
            'max_teachers' => 'required|integer|min:1',
            'calendar_system' => 'required|in:ethiopian,gregorian',
        ]);

        $validated['hmac_secret'] = School::generateHmacSecret();
        $validated['is_active'] = true;
        $validated['subscription_status'] = 'active';
        $validated['subscription_expires_at'] = now()->addYear();

        School::create($validated);

        return redirect()->route('admin.schools.index')->with('success', 'School created successfully.');
    }

    public function edit(School $school)
    {
        return view('admin.schools.form', compact('school'));
    }

    public function update(Request $request, School $school)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'code'         => 'required|string|max:20|unique:schools,code,' . $school->id,
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:20',
            'address'      => 'nullable|string|max:500',
            'plan_tier'    => 'required|in:starter,standard,premium',
            'max_students' => 'required|integer|min:1',
            'max_teachers' => 'required|integer|min:1',
            'is_active'    => 'boolean',
            'calendar_system' => 'required|in:ethiopian,gregorian',
            'subscription_status' => 'required|in:active,grace,expired,suspended',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $school->update($validated);

        return redirect()->route('admin.schools.index')->with('success', 'School updated successfully.');
    }

    public function show(School $school)
    {
        $school->loadCount(['students', 'users']);
        $school->load(['users' => fn($q) => $q->where('role', 'school_admin')->take(3)]);

        return view('admin.schools.show', compact('school'));
    }
}
