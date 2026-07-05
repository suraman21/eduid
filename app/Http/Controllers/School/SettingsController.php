<?php

namespace App\Http\Controllers\School;

use App\Models\AuditLog;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * School configuration hub (admin-only ring at the route level).
 *
 * One place for the operational switches a school admin owns:
 *   · attendance cadence — once daily / twice daily (morning+afternoon)
 *   · brand accent colour (drives web panel + printed cards)
 *   · contact details shown on documents
 * Working days live on the Timetable page (deep-linked from here) because
 * changing them interacts with scheduled lessons.
 */
class SettingsController extends BaseSchoolController
{
    public function index()
    {
        $this->requireAdmin();
        $school = $this->currentSchool();

        return Inertia::render('School/Settings/Index', [
            'settings' => [
                'name'               => $school->name,
                'code'               => $school->code,
                'academic_year'      => $school->academic_year,
                'plan_tier'          => $school->plan_tier,
                'attendance_cadence' => $school->attendance_cadence ?? 'twice',
                'accent_color'       => $school->accent_color,
                'phone'              => $school->phone,
                'email'              => $school->email,
                'address'            => $school->address,
                'working_days'       => $school->workingDays(),
            ],
            'has_subject_attendance' => $school->hasFeature('attendance_subject'),
        ]);
    }

    public function update(Request $request)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $validated = $request->validate([
            'attendance_cadence' => ['required', Rule::in(School::CADENCES)],
            'accent_color'       => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'phone'              => 'nullable|string|max:20',
            'email'              => 'nullable|email|max:255',
            'address'            => 'nullable|string|max:500',
        ]);

        // Per-period cadence needs the per-subject attendance module.
        if ($validated['attendance_cadence'] === 'per_period'
            && ! $this->currentSchool()->hasFeature('attendance_subject')) {
            return back()->with('error',
                'Per-period attendance requires the Per-Subject Attendance module. Contact Arkeon to add it to your plan.');
        }

        $school = $this->currentSchool();
        $old = $school->only(array_keys($validated));
        $school->update($validated);

        AuditLog::log('school.settings_updated', 'schools', $school->id, $old, $validated);

        return back()->with('success', 'Settings saved.');
    }
}
