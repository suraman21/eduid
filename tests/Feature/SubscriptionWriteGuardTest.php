<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regression guards for the audit finding: write endpoints that skipped
 * requireWritableSubscription() let expired schools keep adding/changing data.
 */
class SubscriptionWriteGuardTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Alpha', 'code' => 'ALPHA', 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 100, 'max_teachers' => 10,
            'hmac_secret' => 'secret', 'academic_year' => '2018 E.C.',
            // EXPIRED — view-only per PRD subscription lifecycle.
            'subscription_status' => 'expired',
        ]);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->student = Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA001',
            'first_name' => 'Abebe', 'last_name' => 'Test', 'gender' => 'male',
            'status' => 'active', 'enrollment_date' => today(),
        ]);
    }

    public function test_student_update_is_blocked_when_subscription_expired(): void
    {
        $this->actingAs($this->admin)->put("/school/students/{$this->student->id}", [
            'first_name' => 'Changed', 'last_name' => 'Name', 'gender' => 'male',
        ])->assertForbidden();

        $this->assertSame('Abebe', $this->student->fresh()->first_name);
    }

    public function test_guardian_update_and_destroy_blocked_when_expired(): void
    {
        $guardian = Guardian::create([
            'school_id' => $this->school->id, 'full_name' => 'Parent One',
            'relationship' => 'father', 'phone' => '+251911000000',
        ]);
        $this->student->guardians()->attach($guardian->id, ['is_primary' => true]);

        $this->actingAs($this->admin)
            ->patch("/school/students/{$this->student->id}/guardians/{$guardian->id}", [
                'full_name' => 'Hacked', 'relationship' => 'father',
            ])->assertForbidden();

        $this->actingAs($this->admin)
            ->delete("/school/students/{$this->student->id}/guardians/{$guardian->id}")
            ->assertForbidden();

        $this->assertSame('Parent One', $guardian->fresh()->full_name);
    }

    public function test_photo_session_store_blocked_when_expired(): void
    {
        $this->markTestSkipped('Photo Session feature disabled 2026-07 (routes commented out).');
        $this->actingAs($this->admin)->post('/school/photo-sessions', [
            'session_type' => 'app_capture',
        ])->assertForbidden();
    }
}
