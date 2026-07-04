<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Onboarding welcome tour (tour-only — no checklist/gamification): the shared
 * tour flags, persistence, and the guard that super admins / guests never
 * receive the payload.
 */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Alpha', 'code' => 'ALPHA', 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 1000, 'max_teachers' => 100,
            'hmac_secret' => 'secret', 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
    }

    public function test_new_admin_gets_unseen_tour_flags(): void
    {
        $this->actingAs($this->admin)->get('/school/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('onboarding.tour_seen', false)
                ->where('onboarding.dismissed', false)
            );
    }

    public function test_marking_tour_seen_persists(): void
    {
        $this->actingAs($this->admin)
            ->patch('/school/onboarding', ['tour_seen' => true])
            ->assertRedirect();

        $this->assertTrue($this->admin->fresh()->onboarding_state['tour_seen']);
    }

    public function test_dismissing_persists(): void
    {
        $this->actingAs($this->admin)
            ->patch('/school/onboarding', ['tour_seen' => true, 'dismissed' => true])
            ->assertRedirect();

        $state = $this->admin->fresh()->onboarding_state;
        $this->assertTrue($state['tour_seen']);
        $this->assertTrue($state['dismissed']);
    }

    public function test_onboarding_payload_has_no_task_progress(): void
    {
        // The tour carries no data-derived task progress anymore.
        $this->actingAs($this->admin)->get('/school/dashboard')
            ->assertInertia(fn ($page) => $page
                ->missing('onboarding.progress')
                ->missing('onboarding.finished_at')
            );
    }

    public function test_super_admin_never_receives_onboarding(): void
    {
        $super = User::create([
            'name' => 'Root', 'email' => 'root@arkeon.test', 'password' => Hash::make('password'),
            'role' => User::ROLE_SUPER_ADMIN, 'school_id' => null, 'is_active' => true,
        ]);

        $this->actingAs($super, 'admin')->get('/admin/dashboard')
            ->assertInertia(fn ($page) => $page->where('onboarding', null));
    }
}
