<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuardianTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private User $teacher;
    private Student $student;

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

        $this->student = Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA-00001',
            'first_name' => 'Abebe', 'last_name' => 'Test', 'gender' => 'male',
            'status' => 'active', 'enrollment_date' => today(),
        ]);
    }

    public function test_admin_can_attach_a_guardian(): void
    {
        $this->actingAs($this->admin)
            ->post("/school/students/{$this->student->id}/guardians", [
                'full_name'    => 'Mr Test',
                'phone'        => '0911000000',
                'relationship' => 'father',
                'is_primary'   => 1,
            ])->assertRedirect();

        $this->assertSame(1, $this->student->guardians()->count());
        $guardian = $this->student->guardians()->first();
        $this->assertSame('Mr Test', $guardian->full_name);
        $this->assertTrue((bool) $guardian->pivot->is_primary);
    }

    public function test_teacher_cannot_attach_a_guardian(): void
    {
        $this->actingAs($this->teacher)
            ->post("/school/students/{$this->student->id}/guardians", [
                'full_name'    => 'Mr Test',
                'relationship' => 'father',
            ])->assertForbidden();

        $this->assertSame(0, $this->student->guardians()->count());
    }

    public function test_setting_a_new_primary_demotes_the_old_one(): void
    {
        $this->actingAs($this->admin)->post("/school/students/{$this->student->id}/guardians", [
            'full_name' => 'First', 'phone' => '0911000001', 'relationship' => 'father', 'is_primary' => 1,
        ]);
        $this->actingAs($this->admin)->post("/school/students/{$this->student->id}/guardians", [
            'full_name' => 'Second', 'phone' => '0911000002', 'relationship' => 'mother', 'is_primary' => 1,
        ]);

        $primaries = $this->student->guardians()->wherePivot('is_primary', true)->get();
        $this->assertCount(1, $primaries);
        $this->assertSame('Second', $primaries->first()->full_name);
    }

    public function test_existing_guardian_is_reused_by_phone(): void
    {
        $other = Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA-00002',
            'first_name' => 'Kebede', 'last_name' => 'Test', 'gender' => 'male',
            'status' => 'active', 'enrollment_date' => today(),
        ]);

        $payload = ['full_name' => 'Shared Parent', 'phone' => '0911999999', 'relationship' => 'guardian'];
        $this->actingAs($this->admin)->post("/school/students/{$this->student->id}/guardians", $payload);
        $this->actingAs($this->admin)->post("/school/students/{$other->id}/guardians", $payload);

        // One guardian row, linked to two students.
        $this->assertSame(1, Guardian::withoutGlobalScope('school')->count());
        $guardian = Guardian::withoutGlobalScope('school')->first();
        $this->assertSame(2, $guardian->students()->count());
    }

    public function test_student_can_be_registered_with_guardians_inline(): void
    {
        $this->actingAs($this->admin)->post('/school/students', [
            'first_name' => 'Sara',
            'last_name'  => 'Kebede',
            'gender'     => 'female',
            'guardians'  => [
                ['full_name' => 'Mr Kebede', 'phone' => '0922000001', 'relationship' => 'father'],
                ['full_name' => 'Mrs Kebede', 'phone' => '0922000002', 'relationship' => 'mother'],
            ],
            'primary_guardian' => 0,
        ])->assertRedirect();

        $student = Student::withoutGlobalScope('school')->where('first_name', 'Sara')->firstOrFail();
        $this->assertSame(2, $student->guardians()->count());

        $primary = $student->guardians()->wherePivot('is_primary', true)->get();
        $this->assertCount(1, $primary);
        $this->assertSame('Mr Kebede', $primary->first()->full_name);
    }

    public function test_blank_guardian_rows_are_ignored(): void
    {
        $this->actingAs($this->admin)->post('/school/students', [
            'first_name' => 'Solo',
            'last_name'  => 'Student',
            'gender'     => 'male',
            'guardians'  => [
                ['full_name' => '', 'phone' => '', 'relationship' => 'guardian'],
            ],
        ])->assertRedirect();

        $student = Student::withoutGlobalScope('school')->where('first_name', 'Solo')->firstOrFail();
        $this->assertSame(0, $student->guardians()->count());
    }

    public function test_sibling_registration_reuses_existing_guardian_by_id(): void
    {
        // First child registered with a new parent.
        $this->actingAs($this->admin)->post('/school/students', [
            'first_name' => 'First', 'last_name' => 'Child', 'gender' => 'male',
            'guardians' => [['full_name' => 'Shared Dad', 'phone' => '0933000000', 'relationship' => 'father']],
            'primary_guardian' => 0,
        ]);

        $guardian = Guardian::withoutGlobalScope('school')->where('phone', '0933000000')->firstOrFail();

        // Second child: the form sends the picked guardian's id (typeahead reuse).
        $this->actingAs($this->admin)->post('/school/students', [
            'first_name' => 'Second', 'last_name' => 'Child', 'gender' => 'female',
            'guardians' => [['id' => $guardian->id, 'full_name' => 'Shared Dad', 'phone' => '0933000000', 'relationship' => 'father']],
            'primary_guardian' => 0,
        ]);

        // Still a single guardian row, now linked to both children.
        $this->assertSame(1, Guardian::withoutGlobalScope('school')->count());
        $this->assertSame(2, $guardian->students()->count());
    }

    public function test_guardian_search_returns_scoped_matches(): void
    {
        Guardian::create(['school_id' => $this->school->id, 'full_name' => 'Almaz Tesfaye', 'phone' => '0944000000', 'relationship' => 'mother']);

        $this->actingAs($this->admin)->getJson('/school/guardians/search?q=Almaz')
            ->assertOk()
            ->assertJsonFragment(['full_name' => 'Almaz Tesfaye']);

        // Too-short query returns empty.
        $this->actingAs($this->admin)->getJson('/school/guardians/search?q=A')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_detaching_orphan_guardian_deletes_it(): void
    {
        $this->actingAs($this->admin)->post("/school/students/{$this->student->id}/guardians", [
            'full_name' => 'Lonely', 'phone' => '0911000003', 'relationship' => 'father',
        ]);
        $guardian = $this->student->guardians()->first();

        $this->actingAs($this->admin)
            ->delete("/school/students/{$this->student->id}/guardians/{$guardian->id}")
            ->assertRedirect();

        $this->assertSame(0, $this->student->guardians()->count());
        $this->assertSame(0, Guardian::withoutGlobalScope('school')->count());
    }
}
