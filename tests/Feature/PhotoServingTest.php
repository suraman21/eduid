<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoServingTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;
    private School $schoolB;
    private User $adminA;
    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = $this->makeSchool('Alpha', 'ALPHA');
        $this->schoolB = $this->makeSchool('Beta', 'BETA');
        $this->adminA  = $this->makeAdmin($this->schoolA, 'a@test.com');
        $this->adminB  = $this->makeAdmin($this->schoolB, 'b@test.com');
    }

    public function test_photo_is_served_through_authenticated_route(): void
    {
        Storage::fake('local');
        $student = $this->makeStudentWithPhoto($this->schoolA);

        $this->actingAs($this->adminA)
            ->get(route('school.students.photo', $student->id))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_photo_url_points_at_the_authenticated_route_not_storage(): void
    {
        $student = $this->makeStudentWithPhoto($this->schoolA);

        $this->assertStringContainsString(
            "/school/students/{$student->id}/photo",
            $student->photo_url
        );
        $this->assertStringNotContainsString('/storage/', $student->photo_url);
    }

    public function test_another_school_cannot_fetch_a_photo(): void
    {
        Storage::fake('local');
        $student = $this->makeStudentWithPhoto($this->schoolA);

        // School B admin requesting School A's student → 404 (route-model
        // binding is scoped by BelongsToSchool, so the id resolves to nothing).
        $this->actingAs($this->adminB)
            ->get(route('school.students.photo', $student->id))
            ->assertNotFound();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $student = $this->makeStudentWithPhoto($this->schoolA);

        $this->get(route('school.students.photo', $student->id))
            ->assertRedirect('/login');
    }

    public function test_student_without_photo_returns_404(): void
    {
        $student = Student::withoutGlobalScope('school')->create([
            'school_id' => $this->schoolA->id,
            'uid' => 'ALPHA-NOPHOTO',
            'first_name' => 'No', 'last_name' => 'Photo',
            'gender' => 'male', 'status' => 'active', 'enrollment_date' => today(),
        ]);

        $this->actingAs($this->adminA)
            ->get(route('school.students.photo', $student->id))
            ->assertNotFound();
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function makeSchool(string $name, string $code): School
    {
        return School::create([
            'name' => $name, 'code' => $code, 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 1000, 'max_teachers' => 100,
            'hmac_secret' => 'secret-' . $code, 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);
    }

    private function makeAdmin(School $school, string $email): User
    {
        return User::create([
            'name' => $email, 'email' => $email, 'password' => Hash::make('password123'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $school->id, 'is_active' => true,
        ]);
    }

    private function makeStudentWithPhoto(School $school): Student
    {
        $path = "schools/{$school->id}/students/photo.jpg";

        // Place a fake file on the private disk (only meaningful when faked).
        Storage::disk('local')->put($path, UploadedFile::fake()->image('photo.jpg')->get());

        return Student::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'uid' => strtoupper($school->code) . '-PHOTO',
            'first_name' => 'Photo', 'last_name' => 'Test',
            'gender' => 'male', 'status' => 'active',
            'enrollment_date' => today(), 'photo_path' => $path,
        ]);
    }
}
