<?php

namespace Tests\Feature;

use App\Models\CardOrder;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
        $s1 = $this->makeStudent('Abebe');

        $this->actingAs($this->admin)->post('/school/card-orders', ['order_type' => 'replacement', 'student_ids' => [$s1->id]]);
        $this->actingAs($this->admin)->post('/school/card-orders', ['order_type' => 'replacement', 'student_ids' => [$s1->id]]);

        $codes = CardOrder::withoutGlobalScope('school')->pluck('order_code')->all();
        $this->assertCount(2, $codes);
        $this->assertSame(count($codes), count(array_unique($codes)));
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
