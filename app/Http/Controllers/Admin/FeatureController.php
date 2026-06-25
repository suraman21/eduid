<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureRegistry;
use App\Models\PlanFeature;
use App\Models\School;
use App\Models\SchoolFeatureOverride;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class FeatureController extends Controller
{
    public function index(Request $request)
    {
        $features  = FeatureRegistry::orderBy('category')->orderBy('label')->get();
        $planFeats = PlanFeature::all()->groupBy('plan_tier');
        $schools   = School::orderBy('name')->get(['id', 'name', 'code', 'plan_tier']);

        $selectedSchool   = null;
        $schoolOverrides  = collect();

        if ($schoolId = $request->get('school_id')) {
            $selectedSchool  = School::find($schoolId);
            $schoolOverrides = SchoolFeatureOverride::where('school_id', $schoolId)->get();
        }

        // Build a flat feature_key → {starter, standard, premium} matrix for the frontend
        $planMatrix = $features->mapWithKeys(fn ($f) => [
            $f->key => [
                'starter'  => (bool) ($planFeats->get('starter')?->firstWhere('feature_key', $f->key)?->is_enabled ?? false),
                'standard' => (bool) ($planFeats->get('standard')?->firstWhere('feature_key', $f->key)?->is_enabled ?? false),
                'premium'  => (bool) ($planFeats->get('premium')?->firstWhere('feature_key', $f->key)?->is_enabled ?? false),
            ],
        ]);

        return Inertia::render('Admin/Features/Index', [
            'features'        => $features->values(),
            'plan_matrix'     => $planMatrix,
            'schools'         => $schools->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'plan_tier' => $s->plan_tier]),
            'selected_school' => $selectedSchool ? ['id' => $selectedSchool->id, 'name' => $selectedSchool->name, 'plan_tier' => $selectedSchool->plan_tier] : null,
            'overrides'       => $schoolOverrides->map(fn ($o) => ['feature_key' => $o->feature_key, 'is_enabled' => (bool) $o->is_enabled])->values(),
            'filters'         => $request->only('school_id'),
        ]);
    }

    public function setOverride(Request $request, School $school)
    {
        $validated = $request->validate([
            'feature_key' => 'required|string|exists:feature_registry,key',
            'is_enabled'  => 'required|in:0,1',
        ]);

        SchoolFeatureOverride::updateOrCreate(
            ['school_id' => $school->id, 'feature_key' => $validated['feature_key']],
            [
                'is_enabled'    => (bool) $validated['is_enabled'],
                'overridden_by' => auth()->id(),
                'overridden_at' => now(),
            ]
        );

        Cache::forget("school.{$school->id}.feature.{$validated['feature_key']}");

        return redirect()
            ->route('admin.features.index', ['school_id' => $school->id])
            ->with('success', "Feature override saved for {$school->name}.");
    }

    public function removeOverride(School $school, string $key)
    {
        SchoolFeatureOverride::where('school_id', $school->id)
            ->where('feature_key', $key)
            ->delete();

        Cache::forget("school.{$school->id}.feature.{$key}");

        return redirect()
            ->route('admin.features.index', ['school_id' => $school->id])
            ->with('success', "Override removed — plan default now applies.");
    }
}
