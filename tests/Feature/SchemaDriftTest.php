<?php

namespace Tests\Feature;

use App\Models\CardOrder;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Support\SchemaHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Schema drift (a deploy that skipped `migrate`) repeatedly 500'd production.
 * These guards make the system degrade + report instead of crash.
 */
class SchemaDriftTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $superAdmin;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Alpha', 'code' => 'ALPHA', 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 100, 'max_teachers' => 10,
            'hmac_secret' => 'secret', 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);
        $this->superAdmin = User::create([
            'name' => 'Super', 'email' => 'super@test.com', 'password' => Hash::make('password'),
            'role' => 'super_admin', 'school_id' => null, 'is_active' => true,
        ]);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
    }

    public function test_pending_migrations_is_zero_on_a_migrated_database(): void
    {
        $this->assertSame(0, SchemaHealth::pendingMigrations());
    }

    public function test_health_endpoint_reports_pending_migrations(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $response->assertJsonPath('pending_migrations', 0);
        $response->assertJsonPath('status', 'ok');
    }

    public function test_admin_dashboard_carries_migration_drift_flag(): void
    {
        $this->actingAs($this->superAdmin)->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('pendingMigrations', 0));
    }

    public function test_sheet_print_wizard_survives_missing_print_tables(): void
    {
        $s1 = Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA001',
            'first_name' => 'Abebe', 'last_name' => 'Test', 'gender' => 'male',
            'status' => 'active', 'enrollment_date' => today(),
        ]);
        $this->actingAs($this->admin)->post('/school/card-orders', [
            'order_type' => 'annual_renewal', 'student_ids' => [$s1->id],
        ]);
        $order = CardOrder::withoutGlobalScope('school')->first();

        // Simulate a deploy where the print-module migrations never ran.
        Schema::drop('print_sheet_slots');
        Schema::drop('print_sheets');
        Schema::drop('printer_profiles');
        Schema::drop('sheet_templates');

        // The wizard page must load (degraded: no sheets/printers) — not 500.
        $this->actingAs($this->superAdmin)
            ->get("/admin/card-orders/{$order->id}/sheet-print")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('sheetTemplates', 0)
                ->has('printers', 0));
    }

    public function test_printers_page_survives_missing_table(): void
    {
        Schema::drop('printer_profiles');
        Schema::drop('sheet_templates');

        $this->actingAs($this->superAdmin)->get('/admin/printers')->assertOk();
    }
}
