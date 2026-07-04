<?php

namespace Tests\Feature;

use App\Models\FeeStructure;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Student fee management: fee-type catalogue, bulk invoice generation, the
 * payment ledger (balance/status derived, never stored), overpayment guard,
 * waiving, and the role + 'fees' feature gates.
 */
class FeeTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private User $teacher;
    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        // Premium tier includes the 'fees' module per the seeded matrix.
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
            'name' => 'Teacher', 'email' => 't@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_TEACHER, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->class = SchoolClass::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'grade' => 'Grade 1', 'section' => 'A',
            'academic_year' => '2018 E.C.', 'is_active' => true,
        ]);
    }

    private function student(string $uid, string $status = 'active'): Student
    {
        return Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => $uid,
            'first_name' => 'S', 'last_name' => $uid, 'gender' => 'male',
            'status' => $status, 'enrollment_date' => today(), 'class_id' => $this->class->id,
        ]);
    }

    private function structure(array $attrs = []): FeeStructure
    {
        return FeeStructure::withoutGlobalScope('school')->create(array_merge([
            'school_id' => $this->school->id, 'name' => 'Tuition', 'amount_etb' => 1000,
            'frequency' => 'annual', 'applies_to' => 'all', 'academic_year' => '2018 E.C.', 'is_active' => true,
        ], $attrs));
    }

    public function test_generate_creates_one_invoice_per_active_student_and_is_idempotent(): void
    {
        $this->student('A1');
        $this->student('A2');
        $this->student('A3', 'graduated'); // inactive → excluded
        $s = $this->structure();

        $this->actingAs($this->admin)->post("/school/fees/structures/{$s->id}/generate")->assertRedirect();
        $this->assertSame(2, StudentInvoice::withoutGlobalScope('school')->count());

        // Re-running must not double-bill.
        $this->actingAs($this->admin)->post("/school/fees/structures/{$s->id}/generate")->assertRedirect();
        $this->assertSame(2, StudentInvoice::withoutGlobalScope('school')->count());
    }

    public function test_partial_then_full_payment_moves_status_and_balance(): void
    {
        $st = $this->student('A1');
        $inv = StudentInvoice::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'student_id' => $st->id,
            'title' => 'Tuition', 'amount_etb' => 1000, 'status' => 'unpaid',
        ]);

        $this->actingAs($this->admin)->post("/school/fees/invoices/{$inv->id}/payments", [
            'amount_etb' => 400, 'method' => 'cash', 'payment_date' => today()->toDateString(),
        ])->assertRedirect();

        $inv->refresh();
        $this->assertSame('partial', $inv->status);
        $this->assertSame(600.0, $inv->balance());

        $this->actingAs($this->admin)->post("/school/fees/invoices/{$inv->id}/payments", [
            'amount_etb' => 600, 'method' => 'cash', 'payment_date' => today()->toDateString(),
        ])->assertRedirect();

        $inv->refresh();
        $this->assertSame('paid', $inv->status);
        $this->assertSame(0.0, $inv->balance());
    }

    public function test_overpayment_is_rejected(): void
    {
        $st = $this->student('A1');
        $inv = StudentInvoice::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'student_id' => $st->id,
            'title' => 'Tuition', 'amount_etb' => 1000, 'status' => 'unpaid',
        ]);

        $this->actingAs($this->admin)->post("/school/fees/invoices/{$inv->id}/payments", [
            'amount_etb' => 1500, 'method' => 'cash', 'payment_date' => today()->toDateString(),
        ])->assertStatus(422);

        $this->assertSame('unpaid', $inv->fresh()->status);
        $this->assertSame(0, $inv->payments()->count());
    }

    public function test_waived_invoice_cannot_take_payment(): void
    {
        $st = $this->student('A1');
        $inv = StudentInvoice::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'student_id' => $st->id,
            'title' => 'Tuition', 'amount_etb' => 1000, 'status' => 'unpaid',
        ]);

        $this->actingAs($this->admin)->patch("/school/fees/invoices/{$inv->id}/waive")->assertRedirect();
        $this->assertSame('waived', $inv->fresh()->status);

        $this->actingAs($this->admin)->post("/school/fees/invoices/{$inv->id}/payments", [
            'amount_etb' => 100, 'method' => 'cash', 'payment_date' => today()->toDateString(),
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, $inv->payments()->count());
    }

    public function test_teacher_cannot_manage_fees(): void
    {
        $this->actingAs($this->teacher)->get('/school/fees')->assertForbidden();
        $this->actingAs($this->teacher)->post('/school/fees/structures', [
            'name' => 'X', 'amount_etb' => 10, 'frequency' => 'annual', 'applies_to' => 'all',
        ])->assertForbidden();
    }

    public function test_school_without_fees_module_is_gated(): void
    {
        // Starter tier lacks the 'fees' module.
        $starter = School::create([
            'name' => 'Beta', 'code' => 'BETA', 'is_active' => true,
            'plan_tier' => 'starter', 'max_students' => 300, 'max_teachers' => 10,
            'hmac_secret' => 'secret2', 'academic_year' => '2018 E.C.', 'subscription_status' => 'active',
        ]);
        $starterAdmin = User::create([
            'name' => 'B', 'email' => 'b@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $starter->id, 'is_active' => true,
        ]);

        $this->actingAs($starterAdmin)->get('/school/fees')->assertForbidden();
    }
}
