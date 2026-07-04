<?php

namespace Tests\Feature;

use App\Models\PlanFeature;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public marketing page's pricing is driven live from the Super Admin's
 * package records (seeded by FeatureSeeder, same as production), so the two
 * never drift. These lock that contract: price, active/inactive filtering,
 * ordering, and the plan×feature matrix all flow through to the public page.
 */
class PublicPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_shows_live_plan_price_and_limits(): void
    {
        // Edit the seeded 'starter' plan (first tier) the way an admin would.
        SubscriptionPlan::where('tier', 'starter')
            ->update(['price_monthly_etb' => 19999, 'max_students' => 777]);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('plans.0.tier', 'starter')
            ->where('plans.0.price_monthly_etb', 19999)
            ->where('plans.0.max_students', 777)
            // Standard is the highlighted tier, starter is not.
            ->where('plans.0.is_popular', false)
        );
    }

    public function test_editing_a_plan_in_admin_reflects_on_public_page(): void
    {
        SubscriptionPlan::where('tier', 'starter')->update(['price_monthly_etb' => 30000]);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('plans.0.price_monthly_etb', 30000)
        );
    }

    public function test_card_price_is_shared_from_the_order_model(): void
    {
        // Single source of truth: the same constant the real order flow bills.
        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('cardAnnualPrice', \App\Models\CardOrder::PRICING['annual_renewal'])
        );
    }

    public function test_inactive_plans_are_hidden_and_ordering_holds(): void
    {
        // Deactivating starter drops it; standard becomes the first card.
        SubscriptionPlan::where('tier', 'starter')->update(['is_active' => false]);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->has('plans', 2)
            ->where('plans.0.tier', 'standard')
            ->where('plans.1.tier', 'premium')
        );
    }

    public function test_feature_matrix_drives_public_feature_bullets(): void
    {
        // Reset the standard tier's matrix to exactly one enabled module.
        PlanFeature::where('plan_tier', 'standard')->delete();
        PlanFeature::create(['plan_tier' => 'standard', 'feature_key' => 'gradebook']);

        $standardIndex = SubscriptionPlan::where('is_active', true)
            ->where('tier', 'standard')->exists() ? 1 : 0;

        $this->get('/')->assertInertia(fn ($page) => $page
            ->where("plans.{$standardIndex}.tier", 'standard')
            ->where("plans.{$standardIndex}.features", ['Gradebook'])
        );

        // Toggling analytics ON for the tier must surface it publicly, ordered
        // by (category, label): "Analytics Dashboard" sorts before "Gradebook".
        PlanFeature::create(['plan_tier' => 'standard', 'feature_key' => 'analytics']);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->where("plans.{$standardIndex}.features", ['Analytics Dashboard', 'Gradebook'])
        );
    }
}
