<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\QrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QrTokenTest extends TestCase
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
            'hmac_secret' => bin2hex(random_bytes(16)), 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
    }

    public function test_creating_a_student_issues_a_qr_token(): void
    {
        $this->actingAs($this->admin)->post('/school/students', [
            'first_name' => 'Abebe', 'last_name' => 'Kebede', 'gender' => 'male',
        ])->assertRedirect();

        $student = Student::withoutGlobalScope('school')->first();
        $this->assertNotNull($student->qr_token);
        $this->assertSame($this->school->academic_year, $student->qr_token_year);
        $this->assertTrue($student->hasValidQrToken());
    }

    public function test_qr_token_verifies_for_correct_school_and_year(): void
    {
        $student = Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA1', 'first_name' => 'A',
            'last_name' => 'B', 'gender' => 'male', 'status' => 'active', 'enrollment_date' => today(),
        ]);

        $service = app(QrTokenService::class);
        $token = $service->generate($student, $this->school);

        $this->assertNotNull($service->verify($token, 'ALPHA'));
        $this->assertNull($service->verify('tampered-token', 'ALPHA'));
    }

    public function test_old_year_token_fails_after_renewal(): void
    {
        $student = Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA2', 'first_name' => 'A',
            'last_name' => 'B', 'gender' => 'male', 'status' => 'active', 'enrollment_date' => today(),
        ]);

        $service = app(QrTokenService::class);
        $oldToken = $service->generate($student, $this->school);

        // School rolls over to a new academic year and the student is re-issued.
        $this->school->update(['academic_year' => '2019 E.C.']);
        $service->regenerate($student->fresh(), $this->school->fresh());

        // The old token must no longer verify.
        $this->assertNull($service->verify($oldToken, 'ALPHA'));
    }
}
