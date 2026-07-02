<?php

namespace Tests\Feature;

use App\Models\PlanFeature;
use App\Models\School;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\FeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Super-admin package management: plan editing, plan×feature matrix
 * toggling (row presence = enabled), and per-school package changes with
 * the never-below-usage seat guard.
 */
class PackageManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);

        $this->superAdmin = User::create([
            'name' => 'Root', 'email' => 'root@arkeon.test', 'password' => Hash::make('password'),
            'role' => User::ROLE_SUPER_ADMIN, 'school_id' => null, 'is_active' => true,
        ]);

        $this->school = School::create([
            'name' => 'Alpha', 'code' => 'ALPHA', 'is_active' => true,
            'plan_tier' => 'starter', 'max_students' => 300, 'max_teachers' => 10,
            'hmac_secret' => 'secret', 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);
    }

    private function actingAsSuperAdmin()
    {
        return $this->actingAs($this->superAdmin, 'admin');
    }

    public function test_packages_page_renders_with_plans_matrix_and_schools(): void
    {
        $this->actingAsSuperAdmin()->get('/admin/packages')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Packages/Index', false)
                ->has('plans', 3)
                ->has('schools', 1)
                // Seeded: gradebook is in the starter tier → matrix must say true.
                ->where('matrix.gradebook.starter', true)
                // Seeded: parent_app is premium-only.
                ->where('matrix.parent_app.starter', false)
                ->where('matrix.parent_app.premium', true)
            );
    }

    public function test_school_user_cannot_reach_packages(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $this->school->id, 'is_active' => true,
        ]);

        $this->actingAs($admin)->get('/admin/packages')->assertForbidden();
    }

    public function test_update_plan_prices_and_limits(): void
    {
        $plan = SubscriptionPlan::where('tier', 'starter')->firstOrFail();

        $this->actingAsSuperAdmin()->patch("/admin/packages/plans/{$plan->id}", [
            'price_monthly_etb' => 15000,
            'price_annual_etb'  => 180000,
            'max_students'      => 350,
            'max_teachers'      => 12,
            'max_school_admins' => 1,
            'description'       => 'Updated starter',
        ])->assertRedirect();

        $plan->refresh();
        $this->assertSame(350, $plan->max_students);
        $this->assertSame('Updated starter', $plan->description);
    }

    public function test_toggle_plan_feature_adds_and_removes_row(): void
    {
        // parent_app is NOT in starter by default.
        $this->assertDatabaseMissing('plan_features', [
            'plan_tier' => 'starter', 'feature_key' => 'parent_app',
        ]);

        $this->actingAsSuperAdmin()->post('/admin/packages/plan-features', [
            'plan_tier' => 'starter', 'feature_key' => 'parent_app', 'is_enabled' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('plan_features', [
            'plan_tier' => 'starter', 'feature_key' => 'parent_app',
        ]);
        $this->assertTrue($this->school->fresh()->hasFeature('parent_app'));

        $this->actingAsSuperAdmin()->post('/admin/packages/plan-features', [
            'plan_tier' => 'starter', 'feature_key' => 'parent_app', 'is_enabled' => false,
        ])->assertRedirect();

        $this->assertDatabaseMissing('plan_features', [
            'plan_tier' => 'starter', 'feature_key' => 'parent_app',
        ]);
        $this->assertFalse($this->school->fresh()->hasFeature('parent_app'));
    }

    public function test_update_school_package_applies_plan_limits(): void
    {
        $this->actingAsSuperAdmin()->patch("/admin/packages/schools/{$this->school->id}", [
            'plan_tier'               => 'standard',
            'apply_plan_limits'       => true,
            'max_students'            => null,
            'max_teachers'            => null,
            'subscription_status'     => 'active',
            'subscription_expires_at' => now()->addYear()->toDateString(),
        ])->assertRedirect();

        $this->school->refresh();
        $this->assertSame('standard', $this->school->plan_tier);
        // Standard plan seeded limits.
        $this->assertSame(800, $this->school->max_students);
        $this->assertSame(30, $this->school->max_teachers);
    }

    public function test_seat_limit_cannot_go_below_current_usage(): void
    {
        // Two active teachers on the books.
        foreach ([1, 2] as $i) {
            User::create([
                'name' => "T{$i}", 'email' => "t{$i}@test.com", 'password' => Hash::make('password'),
                'role' => User::ROLE_TEACHER, 'school_id' => $this->school->id, 'is_active' => true,
            ]);
        }

        $this->actingAsSuperAdmin()->patch("/admin/packages/schools/{$this->school->id}", [
            'plan_tier'               => 'starter',
            'apply_plan_limits'       => false,
            'max_students'            => 300,
            'max_teachers'            => 1, // below the 2 in use
            'subscription_status'     => 'active',
            'subscription_expires_at' => null,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(10, $this->school->fresh()->max_teachers);
    }

    public function test_features_page_matrix_reflects_plan_rows(): void
    {
        // Regression: the matrix used to read a non-existent is_enabled column
        // on plan_features and rendered everything as false.
        $this->assertTrue(
            PlanFeature::where('plan_tier', 'starter')->where('feature_key', 'gradebook')->exists()
        );

        $this->actingAsSuperAdmin()->get('/admin/features')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Features/Index', false)
                ->where('plan_matrix.gradebook.starter', true)
            );
    }
}
