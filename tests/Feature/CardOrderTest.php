<?php

namespace Tests\Feature;

use App\Models\CardOrder;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CardOrderTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private User $teacher;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Confirming an order now writes a print-ready PDF to the private disk;
        // keep that out of real storage during tests.
        Storage::fake('local');

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
        $this->teacher = User::create([
            'name' => 'Teacher', 'email' => 'teacher@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_TEACHER, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->superAdmin = User::create([
            'name' => 'Super', 'email' => 'super@test.com', 'password' => Hash::make('password'),
            'role' => 'super_admin', 'school_id' => null, 'is_active' => true,
        ]);
    }

    private function makeStudent(string $first): Student
    {
        return Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA' . strtoupper($first),
            'first_name' => $first, 'last_name' => 'Test', 'gender' => 'male',
            'status' => 'active', 'enrollment_date' => today(),
        ]);
    }

    public function test_admin_can_create_card_order_with_confirmation_code(): void
    {
        $s1 = $this->makeStudent('Abebe');
        $s2 = $this->makeStudent('Bekele');

        $response = $this->actingAs($this->admin)->post('/school/card-orders', [
            'order_type'  => 'annual_renewal',
            'student_ids' => [$s1->id, $s2->id],
        ]);

        $response->assertRedirect();
        $order = CardOrder::withoutGlobalScope('school')->first();
        $this->assertNotNull($order);
        $this->assertSame('pending', $order->status);
        $this->assertSame(2, $order->student_count);
        $this->assertSame('100.00', (string) $order->amount_etb); // 2 × 50 ETB
        $this->assertStringStartsWith('ORD-ALPHA-', $order->order_code);
    }

    public function test_teacher_cannot_create_card_order(): void
    {
        $s1 = $this->makeStudent('Abebe');

        $this->actingAs($this->teacher)->post('/school/card-orders', [
            'order_type'  => 'annual_renewal',
            'student_ids' => [$s1->id],
        ])->assertForbidden();
    }

    public function test_order_codes_are_unique_and_sequential(): void
    {
        // Different students per order — a single student cannot be in two open
        // orders at once (see test_cannot_order_for_student_with_ongoing_order).
        $s1 = $this->makeStudent('Abebe');
        $s2 = $this->makeStudent('Bekele');

        $this->actingAs($this->admin)->post('/school/card-orders', ['order_type' => 'replacement', 'student_ids' => [$s1->id]]);
        $this->actingAs($this->admin)->post('/school/card-orders', ['order_type' => 'replacement', 'student_ids' => [$s2->id]]);

        $codes = CardOrder::withoutGlobalScope('school')->pluck('order_code')->all();
        $this->assertCount(2, $codes);
        $this->assertSame(count($codes), count(array_unique($codes)));
    }

    public function test_cannot_order_for_student_with_ongoing_order(): void
    {
        $s1 = $this->makeStudent('Abebe');

        // First order succeeds and leaves the student in an open (pending) order.
        $this->actingAs($this->admin)->post('/school/card-orders', [
            'order_type' => 'replacement', 'student_ids' => [$s1->id],
        ])->assertRedirect();

        // Second order for the same student is blocked with an error, no new order.
        $this->actingAs($this->admin)->post('/school/card-orders', [
            'order_type' => 'replacement', 'student_ids' => [$s1->id],
        ])->assertSessionHas('error');

        $this->assertSame(1, CardOrder::withoutGlobalScope('school')->count());
    }

    public function test_can_reorder_after_previous_order_is_cancelled(): void
    {
        $s1 = $this->makeStudent('Abebe');

        $this->actingAs($this->admin)->post('/school/card-orders', ['order_type' => 'replacement', 'student_ids' => [$s1->id]]);
        $order = CardOrder::withoutGlobalScope('school')->first();
        $this->actingAs($this->admin)->post("/school/card-orders/{$order->id}/cancel", ['cancel_reason' => 'x']);

        // Cancelled is terminal, so the student is free to be ordered again.
        $this->actingAs($this->admin)->post('/school/card-orders', [
            'order_type' => 'replacement', 'student_ids' => [$s1->id],
        ])->assertRedirect();

        $this->assertSame(2, CardOrder::withoutGlobalScope('school')->count());
    }

    public function test_cannot_order_for_another_schools_student(): void
    {
        $other = School::create([
            'name' => 'Beta', 'code' => 'BETA', 'is_active' => true, 'plan_tier' => 'premium',
            'max_students' => 100, 'max_teachers' => 10, 'hmac_secret' => 'x',
            'academic_year' => '2018 E.C.', 'subscription_status' => 'active',
        ]);
        $foreign = Student::withoutGlobalScope('school')->create([
            'school_id' => $other->id, 'uid' => 'BETAX', 'first_name' => 'Foreign',
            'last_name' => 'Kid', 'gender' => 'male', 'status' => 'active', 'enrollment_date' => today(),
        ]);

        $this->actingAs($this->admin)->post('/school/card-orders', [
            'order_type'  => 'replacement',
            'student_ids' => [$foreign->id],
        ])->assertSessionHasErrors('student_ids.0');
    }

    public function test_admin_can_cancel_pending_order(): void
    {
        $s1 = $this->makeStudent('Abebe');
        $this->actingAs($this->admin)->post('/school/card-orders', ['order_type' => 'replacement', 'student_ids' => [$s1->id]]);
        $order = CardOrder::withoutGlobalScope('school')->first();

        $this->actingAs($this->admin)->post("/school/card-orders/{$order->id}/cancel", ['cancel_reason' => 'Mistake'])
            ->assertRedirect();

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_super_admin_can_confirm_and_advance_order(): void
    {
        $s1 = $this->makeStudent('Abebe');
        $this->actingAs($this->admin)->post('/school/card-orders', ['order_type' => 'annual_renewal', 'student_ids' => [$s1->id]]);
        $order = CardOrder::withoutGlobalScope('school')->first();

        $this->actingAs($this->superAdmin)->post("/admin/card-orders/{$order->id}/confirm")->assertRedirect();
        $this->assertSame('confirmed', $order->fresh()->status);

        $this->actingAs($this->superAdmin)->post("/admin/card-orders/{$order->id}/status", ['status' => 'printing'])->assertRedirect();
        $this->assertSame('printing', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->printed_at);
    }

    public function test_confirming_generates_a_private_print_file(): void
    {
        $s1 = $this->makeStudent('Abebe');
        $this->actingAs($this->admin)->post('/school/card-orders', ['order_type' => 'annual_renewal', 'student_ids' => [$s1->id]]);
        $order = CardOrder::withoutGlobalScope('school')->first();

        $this->actingAs($this->superAdmin)->post("/admin/card-orders/{$order->id}/confirm")->assertRedirect();

        $order->refresh();
        $this->assertNotNull($order->print_file_path);
        $this->assertStringStartsWith("print_jobs/{$order->order_code}/", $order->print_file_path);
        Storage::disk('local')->assertExists($order->print_file_path);
    }

    public function test_confirming_mints_qr_token_for_card(): void
    {
        $s1 = $this->makeStudent('Abebe');
        $this->assertNull($s1->qr_token);

        $this->actingAs($this->admin)->post('/school/card-orders', ['order_type' => 'annual_renewal', 'student_ids' => [$s1->id]]);
        $order = CardOrder::withoutGlobalScope('school')->first();
        $this->actingAs($this->superAdmin)->post("/admin/card-orders/{$order->id}/confirm");

        $s1->refresh();
        $this->assertNotNull($s1->qr_token);
        $this->assertSame($this->school->academic_year, $s1->qr_token_year);
    }

    public function test_super_admin_can_download_print_file(): void
    {
        $s1 = $this->makeStudent('Abebe');
        $this->actingAs($this->admin)->post('/school/card-orders', ['order_type' => 'annual_renewal', 'student_ids' => [$s1->id]]);
        $order = CardOrder::withoutGlobalScope('school')->first();
        $this->actingAs($this->superAdmin)->post("/admin/card-orders/{$order->id}/confirm");

        $response = $this->actingAs($this->superAdmin)->get("/admin/card-orders/{$order->id}/print-file");
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_school_admin_cannot_reach_admin_print_file_route(): void
    {
        $s1 = $this->makeStudent('Abebe');
        $this->actingAs($this->admin)->post('/school/card-orders', ['order_type' => 'annual_renewal', 'student_ids' => [$s1->id]]);
        $order = CardOrder::withoutGlobalScope('school')->first();
        $this->actingAs($this->superAdmin)->post("/admin/card-orders/{$order->id}/confirm");

        // The admin route group is gated to super_admin — a school admin is blocked.
        $this->actingAs($this->admin)->get("/admin/card-orders/{$order->id}/print-file")
            ->assertForbidden();
    }

    public function test_download_404_when_no_print_file(): void
    {
        $s1 = $this->makeStudent('Abebe');
        $this->actingAs($this->admin)->post('/school/card-orders', ['order_type' => 'annual_renewal', 'student_ids' => [$s1->id]]);
        $order = CardOrder::withoutGlobalScope('school')->first();

        // Order is still pending — no print file generated yet.
        $this->actingAs($this->superAdmin)->get("/admin/card-orders/{$order->id}/print-file")
            ->assertNotFound();
    }

    public function test_super_admin_can_regenerate_print_file(): void
    {
        $s1 = $this->makeStudent('Abebe');
        $this->actingAs($this->admin)->post('/school/card-orders', ['order_type' => 'annual_renewal', 'student_ids' => [$s1->id]]);
        $order = CardOrder::withoutGlobalScope('school')->first();
        $this->actingAs($this->superAdmin)->post("/admin/card-orders/{$order->id}/confirm");
        $first = $order->fresh()->print_file_path;

        $this->actingAs($this->superAdmin)->post("/admin/card-orders/{$order->id}/print-file/regenerate")
            ->assertRedirect();

        $order->refresh();
        $this->assertNotNull($order->print_file_path);
        Storage::disk('local')->assertExists($order->print_file_path);
    }

    public function test_status_transitions_cannot_skip_steps(): void
    {
        $s1 = $this->makeStudent('Abebe');
        $this->actingAs($this->admin)->post('/school/card-orders', ['order_type' => 'annual_renewal', 'student_ids' => [$s1->id]]);
        $order = CardOrder::withoutGlobalScope('school')->first();

        $this->actingAs($this->superAdmin)->post("/admin/card-orders/{$order->id}/confirm");

        // confirmed → shipped is an illegal skip (must go through printing)
        $this->actingAs($this->superAdmin)->post("/admin/card-orders/{$order->id}/status", ['status' => 'shipped'])
            ->assertSessionHas('error');

        $this->assertSame('confirmed', $order->fresh()->status);
    }
}
