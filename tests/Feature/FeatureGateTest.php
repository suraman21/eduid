<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolFeatureOverride;
use App\Models\User;
use Database\Seeders\FeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The school-panel half of the Packages system: 'feature:*' route middleware
 * must block modules a school's package does not include, and per-school
 * overrides must win over plan defaults.
 */
class FeatureGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FeatureSeeder::class);
    }

    private function makeSchool(string $tier): School
    {
        return School::create([
            'name' => "School {$tier}", 'code' => strtoupper($tier), 'is_active' => true,
            'plan_tier' => $tier, 'max_students' => 300, 'max_teachers' => 10,
            'hmac_secret' => 'secret-' . $tier, 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);
    }

    private function makeAdmin(School $school): User
    {
        return User::create([
            'name' => 'Admin', 'email' => "admin@{$school->code}.test",
            'password' => Hash::make('password'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $school->id, 'is_active' => true,
        ]);
    }

    public function test_starter_school_cannot_use_csv_import(): void
    {
        // Seeded packages: csv_import is Standard and above.
        $admin = $this->makeAdmin($this->makeSchool('starter'));

        $this->actingAs($admin)->get('/school/students/import')->assertForbidden();
        $this->actingAs($admin)->post('/school/students/import', [])->assertForbidden();
    }

    public function test_standard_school_can_use_csv_import(): void
    {
        $admin = $this->makeAdmin($this->makeSchool('standard'));

        $this->actingAs($admin)->get('/school/students/import')->assertOk();
    }

    public function test_override_can_grant_a_module_beyond_the_plan(): void
    {
        $school = $this->makeSchool('starter');
        $admin  = $this->makeAdmin($school);

        SchoolFeatureOverride::create([
            'school_id' => $school->id, 'feature_key' => 'csv_import', 'is_enabled' => true,
        ]);
        $school->flushFeatureCache();

        $this->actingAs($admin)->get('/school/students/import')->assertOk();
    }

    public function test_override_can_revoke_a_module_included_in_the_plan(): void
    {
        $school = $this->makeSchool('premium');
        $admin  = $this->makeAdmin($school);

        // Premium includes gradebook — kill-switch it for this school.
        SchoolFeatureOverride::create([
            'school_id' => $school->id, 'feature_key' => 'gradebook', 'is_enabled' => false,
        ]);
        $school->flushFeatureCache();

        $this->actingAs($admin)->get('/school/grades')->assertForbidden();

        // Report cards (separate module) still works.
        $this->actingAs($admin)->get('/school/report-cards')->assertOk();
    }

    public function test_gradebook_open_when_included(): void
    {
        $admin = $this->makeAdmin($this->makeSchool('starter'));

        // Starter includes gradebook + report_cards per the seeded matrix.
        $this->actingAs($admin)->get('/school/grades')->assertOk();
        $this->actingAs($admin)->get('/school/report-cards')->assertOk();
    }

    public function test_feature_flags_are_shared_with_the_frontend(): void
    {
        $admin = $this->makeAdmin($this->makeSchool('starter'));

        $this->actingAs($admin)->get('/school/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('features.gradebook', true)
                ->where('features.csv_import', false)
            );
    }
}
