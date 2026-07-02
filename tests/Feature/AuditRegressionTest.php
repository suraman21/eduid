<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\SubscriptionPlan;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\User;
use Database\Seeders\FeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regression guards for the deep-audit findings. Each test pins a specific
 * silent killer so it can never quietly return:
 *
 *  1. FeatureSeeder must NOT clobber Packages-page customizations on deploy
 *  2. API login must respect can_login (revoked accounts minted tokens)
 *  3. Teacher viewing a CLASSLESS student → 403, not a TypeError 500
 *  4. Staff pages must not bind parent accounts (role-flip via staff edit)
 *  5. An admin cannot demote/deactivate their own account (school lockout)
 *  6. Year transition purges the old year's timetable + is_current terms
 *  7. Web login parent-block must not leak account existence pre-password
 */
class AuditRegressionTest extends TestCase
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

    // 1 ─ seeder must preserve live business decisions
    public function test_reseeding_preserves_plan_price_and_feature_matrix_edits(): void
    {
        // Super admin customizes: new starter price + grants parent_app to starter.
        SubscriptionPlan::where('tier', 'starter')->update(['price_monthly_etb' => 99999]);
        DB::table('plan_features')->insert([
            'plan_tier' => 'starter', 'feature_key' => 'parent_app',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // ...and removes gradebook from starter.
        DB::table('plan_features')->where('plan_tier', 'starter')
            ->where('feature_key', 'gradebook')->delete();

        // Deploy re-runs the seeder.
        $this->seed(FeatureSeeder::class);

        $this->assertEquals(99999, (float) SubscriptionPlan::where('tier', 'starter')->value('price_monthly_etb'));
        $this->assertDatabaseHas('plan_features', ['plan_tier' => 'starter', 'feature_key' => 'parent_app']);
        $this->assertDatabaseMissing('plan_features', ['plan_tier' => 'starter', 'feature_key' => 'gradebook']);
    }

    // 2 ─ API can_login
    public function test_api_login_rejects_revoked_accounts(): void
    {
        User::create([
            'name' => 'Revoked', 'email' => 'revoked@test.com',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_TEACHER, 'school_id' => $this->school->id,
            'is_active' => true, 'can_login' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'school_code' => 'ALPHA', 'email' => 'revoked@test.com',
            'password' => 'password123', 'device_name' => 'test',
        ])->assertStatus(403);
    }

    // 3 ─ classless student must 403 for teachers, not crash
    public function test_teacher_viewing_classless_student_gets_403_not_500(): void
    {
        $teacher = User::create([
            'name' => 'T', 'email' => 't@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_TEACHER, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $classless = Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA-2018-000009',
            'first_name' => 'No', 'last_name' => 'Class', 'gender' => 'male',
            'status' => 'active', 'enrollment_date' => today(), 'class_id' => null,
        ]);

        $this->actingAs($teacher)
            ->get("/school/students/{$classless->id}/id-card")
            ->assertForbidden();
    }

    // 4 ─ parent accounts invisible to staff management
    public function test_staff_pages_cannot_bind_a_parent_account(): void
    {
        $parent = User::create([
            'name' => 'Parent', 'email' => 'p@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_PARENT, 'school_id' => $this->school->id, 'is_active' => true,
        ]);

        $this->actingAs($this->admin)->get("/school/staff/{$parent->id}")->assertNotFound();
        $this->actingAs($this->admin)->get("/school/staff/{$parent->id}/edit")->assertNotFound();
        $this->actingAs($this->admin)->put("/school/staff/{$parent->id}", [
            'name' => 'Hijacked', 'email' => 'p@test.com', 'role' => 'teacher',
        ])->assertNotFound();

        $this->assertSame('parent', $parent->fresh()->role);
    }

    // 5 ─ self-lockout guard
    public function test_admin_cannot_demote_or_deactivate_themselves(): void
    {
        foreach ([
            ['role' => 'teacher', 'is_active' => true,  'can_login' => true],
            ['role' => 'school_admin', 'is_active' => false, 'can_login' => true],
            ['role' => 'school_admin', 'is_active' => true,  'can_login' => false],
        ] as $attempt) {
            $this->actingAs($this->admin)->put("/school/staff/{$this->admin->id}", array_merge([
                'name' => 'Admin', 'email' => 'admin@test.com',
            ], $attempt))->assertRedirect()->assertSessionHas('error');
        }

        $fresh = $this->admin->fresh();
        $this->assertSame('school_admin', $fresh->role);
        $this->assertTrue((bool) $fresh->is_active);
    }

    // 6 ─ year transition clears stale timetable + current-term flags
    public function test_year_transition_purges_old_timetable_and_current_terms(): void
    {
        $class = SchoolClass::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'grade' => 'Grade 1', 'section' => 'A',
            'academic_year' => '2018 E.C.', 'level_order' => 2, 'is_active' => true,
        ]);
        $period = TimetablePeriod::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'label' => 'P1',
            'start_time' => '08:00', 'end_time' => '08:40', 'sort_order' => 1,
        ]);
        TimetableEntry::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'class_id' => $class->id,
            'period_id' => $period->id, 'day_of_week' => 1,
        ]);
        $oldTerm = Term::create([
            'school_id' => $this->school->id, 'academic_year' => '2018 E.C.',
            'name' => 'Term 2', 'start_date' => today()->subMonth(),
            'end_date' => today()->addMonth(), 'is_current' => true,
        ]);

        $this->actingAs($this->admin)->post('/school/year-transition', [
            'new_year' => '2019 E.C.', 'confirmed' => true,
            'mappings' => [
                ['class_id' => $class->id, 'action' => 'promote', 'target_grade' => 'Grade 2'],
            ],
        ])->assertRedirect();

        // Old-year lessons are gone; the bell schedule survives.
        $this->assertDatabaseMissing('timetable_entries', ['class_id' => $class->id]);
        $this->assertDatabaseHas('timetable_periods', ['id' => $period->id]);

        // No stale "current" term from last year.
        $this->assertFalse((bool) $oldTerm->fresh()->is_current);
    }

    // 7 ─ no account-existence leak before password verification
    public function test_wrong_password_on_parent_account_does_not_reveal_it_is_a_parent(): void
    {
        User::create([
            'name' => 'Parent', 'email' => 'parent@test.com',
            'password' => Hash::make('correct-password'),
            'role' => User::ROLE_PARENT, 'school_id' => $this->school->id, 'is_active' => true,
        ]);

        $response = $this->from('/login')->post('/login', [
            'school_code' => 'ALPHA', 'email' => 'parent@test.com', 'password' => 'wrong',
        ]);

        // Same generic message as any bad credential — never the
        // "parents use the mobile app" hint.
        $response->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

        // With the RIGHT password the redirect-to-app message appears.
        $this->from('/login')->post('/login', [
            'school_code' => 'ALPHA', 'email' => 'parent@test.com', 'password' => 'correct-password',
        ])->assertSessionHasErrors(['email' => 'Parent accounts sign in through the EduID mobile app, not the web portal.']);

        $this->assertGuest();
    }
}
