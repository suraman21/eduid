<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FeatureRegistry;
use App\Models\PlanFeature;
use App\Models\School;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Package management — the PRD's revenue engine in one place (PRD §11 / §13):
 *
 *   · the three plan tiers (pricing, seat limits) — editable
 *   · which feature modules each tier includes — the plan×feature matrix
 *   · every school's package: tier, seats, subscription status & expiry
 *
 * Per-school feature OVERRIDES (comp a module, kill-switch one) stay on the
 * existing Features page; this page manages the defaults schools inherit.
 */
class PackageController extends Controller
{
    public function index()
    {
        $tierOrder = ['starter' => 0, 'standard' => 1, 'premium' => 2];
        $plans = SubscriptionPlan::all()
            ->sortBy(fn ($p) => $tierOrder[$p->tier] ?? 9)->values();

        $features = FeatureRegistry::orderBy('category')->orderBy('label')->get();

        // plan×feature matrix: ROW PRESENCE in plan_features = enabled.
        $enabled = PlanFeature::all()->groupBy('plan_tier')
            ->map(fn ($rows) => $rows->pluck('feature_key')->flip());

        $matrix = $features->mapWithKeys(fn ($f) => [
            $f->key => [
                'starter'  => $enabled->get('starter')?->has($f->key) ?? false,
                'standard' => $enabled->get('standard')?->has($f->key) ?? false,
                'premium'  => $enabled->get('premium')?->has($f->key) ?? false,
            ],
        ]);

        $schools = School::orderBy('name')->get()->map(fn ($s) => [
            'id'                      => $s->id,
            'name'                    => $s->name,
            'code'                    => $s->code,
            'plan_tier'               => $s->plan_tier,
            'max_students'            => $s->max_students,
            'max_teachers'            => $s->max_teachers,
            'students_used'           => $s->activeStudentCount(),
            'teachers_used'           => $s->activeTeacherCount(),
            'subscription_status'     => $s->subscription_status ?? 'active',
            'subscription_expires_at' => $s->subscription_expires_at?->toDateString(),
            'override_count'          => $s->featureOverrides()->count(),
        ]);

        return Inertia::render('Admin/Packages/Index', [
            'plans' => $plans->map(fn ($p) => [
                'id'                => $p->id,
                'name'              => $p->name,
                'tier'              => $p->tier,
                'price_monthly_etb' => (float) $p->price_monthly_etb,
                'price_annual_etb'  => (float) $p->price_annual_etb,
                'max_students'      => $p->max_students,
                'max_teachers'      => $p->max_teachers,
                'max_school_admins' => $p->max_school_admins,
                'description'       => $p->description,
                'is_active'         => (bool) $p->is_active,
                'school_count'      => $p->schools()->count(),
            ])->values(),
            'features' => $features->map(fn ($f) => [
                'key'      => $f->key,
                'label'    => $f->label,
                'category' => $f->category,
            ])->values(),
            'matrix'  => $matrix,
            'schools' => $schools,
        ]);
    }

    /** Edit a plan's pricing / seat limits. */
    public function updatePlan(Request $request, SubscriptionPlan $plan)
    {
        $validated = $request->validate([
            'price_monthly_etb' => 'required|numeric|min:0',
            'price_annual_etb'  => 'required|numeric|min:0',
            'max_students'      => 'required|integer|min:1',
            'max_teachers'      => 'required|integer|min:1',
            'max_school_admins' => 'required|integer|min:1',
            'description'       => 'nullable|string|max:500',
        ]);

        $old = $plan->only(array_keys($validated));
        $plan->update($validated);

        AuditLog::log('package.plan_updated', 'subscription_plans', $plan->id, $old, $validated);

        return back()->with('success', "{$plan->name} plan updated. Existing schools keep their current seat limits until you change them per school.");
    }

    /** Toggle a feature module on/off for a plan tier. */
    public function togglePlanFeature(Request $request)
    {
        $validated = $request->validate([
            'plan_tier'   => ['required', Rule::in(['starter', 'standard', 'premium'])],
            'feature_key' => 'required|string|exists:feature_registry,key',
            'is_enabled'  => 'required|boolean',
        ]);

        if ($validated['is_enabled']) {
            PlanFeature::firstOrCreate([
                'plan_tier'   => $validated['plan_tier'],
                'feature_key' => $validated['feature_key'],
            ]);
        } else {
            PlanFeature::where('plan_tier', $validated['plan_tier'])
                ->where('feature_key', $validated['feature_key'])
                ->delete();
        }

        // Every school on this tier inherits the change — drop their cached
        // feature resolutions so it applies immediately, not in an hour.
        School::where('plan_tier', $validated['plan_tier'])->get()
            ->each->flushFeatureCache();

        AuditLog::log('package.plan_feature_toggled', 'plan_features', null, null, $validated);

        return back()->with('success', 'Plan features updated.');
    }

    /** Change one school's package: tier, seats, subscription status/expiry. */
    public function updateSchoolPackage(Request $request, School $school)
    {
        $validated = $request->validate([
            'plan_tier'               => ['required', Rule::in(['starter', 'standard', 'premium'])],
            'apply_plan_limits'       => 'boolean',
            'max_students'            => 'required_unless:apply_plan_limits,true|nullable|integer|min:1',
            'max_teachers'            => 'required_unless:apply_plan_limits,true|nullable|integer|min:1',
            'subscription_status'     => ['required', Rule::in(['active', 'grace', 'expired', 'suspended'])],
            'subscription_expires_at' => 'nullable|date',
        ]);

        if ($request->boolean('apply_plan_limits')) {
            $plan = SubscriptionPlan::where('tier', $validated['plan_tier'])->first();
            if ($plan) {
                $validated['max_students'] = $plan->max_students;
                $validated['max_teachers'] = $plan->max_teachers;
            }
        }
        unset($validated['apply_plan_limits']);

        // Guard against silently stranding a school: seats can be lowered, but
        // never below current usage — that would break every enrolment write.
        $activeStudents = $school->activeStudentCount();
        $activeTeachers = $school->activeTeacherCount();
        if ($validated['max_students'] < $activeStudents) {
            return back()->with('error',
                "Cannot set the student limit to {$validated['max_students']}: {$school->name} already has {$activeStudents} active students.");
        }
        if ($validated['max_teachers'] < $activeTeachers) {
            return back()->with('error',
                "Cannot set the teacher limit to {$validated['max_teachers']}: {$school->name} already has {$activeTeachers} active teachers.");
        }

        $old = $school->only(array_keys($validated));
        $school->update($validated);
        $school->flushFeatureCache();

        AuditLog::log('package.school_updated', 'schools', $school->id, $old, $validated);

        return back()->with('success', "{$school->name}'s package updated.");
    }
}
