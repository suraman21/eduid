<?php

namespace App\Http\Controllers;

use App\Models\FeatureRegistry;
use App\Models\PlanFeature;
use App\Models\SubscriptionPlan;

/**
 * Public marketing site. The pricing shown here is NOT hardcoded — it is
 * assembled live from the same records the Super Admin edits on the Packages
 * page (subscription_plans + the plan×feature matrix). Editing a plan's price,
 * seat limits, description, or toggling a feature module for a tier is
 * therefore reflected on the public site immediately, with no code change.
 */
class PublicController extends Controller
{
    /**
     * Build the public pricing payload: every ACTIVE plan tier with its live
     * price, seat limits, and the human-readable labels of the feature modules
     * that tier currently includes (straight from the admin feature matrix).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function plans(): array
    {
        $tierOrder = ['starter' => 0, 'standard' => 1, 'premium' => 2];

        // Registry labels in a stable, presentable order (category, then label).
        $registry = FeatureRegistry::orderBy('category')->orderBy('label')->get();

        // Which feature_keys are enabled per tier (row presence = enabled).
        $enabledByTier = PlanFeature::all()
            ->groupBy('plan_tier')
            ->map(fn ($rows) => $rows->pluck('feature_key')->flip());

        return SubscriptionPlan::where('is_active', true)->get()
            ->sortBy(fn ($p) => $tierOrder[$p->tier] ?? 9)
            ->map(function (SubscriptionPlan $p) use ($registry, $enabledByTier) {
                $enabled = $enabledByTier->get($p->tier);

                // Ordered, human-readable module names this tier includes.
                $features = $registry
                    ->filter(fn ($f) => $enabled?->has($f->key) ?? false)
                    ->pluck('label')
                    ->values()
                    ->all();

                return [
                    'name'              => $p->name,
                    'tier'              => $p->tier,
                    'price_monthly_etb' => (float) $p->price_monthly_etb,
                    'price_annual_etb'  => $p->price_annual_etb !== null ? (float) $p->price_annual_etb : null,
                    'max_students'      => $p->max_students,
                    'max_teachers'      => $p->max_teachers,
                    'max_school_admins' => $p->max_school_admins,
                    'description'       => $p->description,
                    // Standard is the highlighted "most popular" tier.
                    'is_popular'        => $p->tier === 'standard',
                    'features'          => $features,
                ];
            })
            ->values()
            ->all();
    }
}
