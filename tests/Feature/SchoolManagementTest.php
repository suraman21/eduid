<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SchoolManagementTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;
    private School $schoolB;
    private User $adminA;
    private User $teacherA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = $this->makeSchool('Alpha Academy', 'ALPHA');
        $this->schoolB = $this->makeSchool('Beta Academy', 'BETA');

        $this->adminA = $this->makeUser($this->schoolA, User::ROLE_SCHOOL_ADMIN, 'admin-a@test.com');
        $this->teacherA = $this->makeUser($this->schoolA, User::ROLE_TEACHER, 'teacher-a@test.com');
    }

    private function makeSchool(string $name, string $code): School
    {
        return School::create([
            'name' => $name,
            'code' => $code,
            'is_active' => true,
            'plan_tier' => 'premium',
            'max_students' => 1000,
            'max_teachers' => 100,
            'hmac_secret' => 'secret-' . $code,
            'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);
    }

    private function makeUser(School $school, string $role, string $email): User
    {
        return User::create([
            'name' => $email,
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => $role,
            'school_id' => $school->id,
            'is_active' => true,
        ]);
    }

    private function makeStudent(School $school, string $first): Student
    {
        return Student::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'uid' => strtoupper($school->code . $first),
            'first_name' => $first,
            'last_name' => 'Test',
            'gender' => 'male',
            'status' => 'active',
            'enrollment_date' => today(),
        ]);
    }

    public function test_school_admin_can_create_a_student(): void
    {
        $response = $this->actingAs($this->adminA)->post('/school/students', [
            'first_name' => 'Abebe',
            'last_name' => 'Kebede',
            'gender' => 'male',
        ]);

        $response->assertRedirect(route('school.students.index'));
        $this->assertDatabaseHas('students', [
            'first_name' => 'Abebe',
            'school_id' => $this->schoolA->id,
        ]);
    }

    public function test_teacher_cannot_create_a_student(): void
    {
        $response = $this->actingAs($this->teacherA)->post('/school/students', [
            'first_name' => 'Hidden',
            'last_name' => 'Student',
            'gender' => 'female',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('students', ['first_name' => 'Hidden']);
    }

    public function test_admin_cannot_view_another_schools_student(): void
    {
        $studentB = $this->makeStudent($this->schoolB, 'Foreign');

        $response = $this->actingAs($this->adminA)->get("/school/students/{$studentB->id}");

        $response->assertNotFound();
    }

    public function test_student_listing_is_scoped_to_own_school(): void
    {
        $this->makeStudent($this->schoolA, 'Mine');
        $this->makeStudent($this->schoolB, 'Theirs');

        $this->actingAs($this->adminA)->get('/school/students')
            ->assertOk()
            ->assertSee('Mine')
            ->assertDontSee('Theirs');
    }

    public function test_subject_code_must_be_unique_within_school_only(): void
    {
        // Same code in school B should not block school A.
        Subject::withoutGlobalScope('school')->create([
            'school_id' => $this->schoolB->id,
            'name' => 'Math B',
            'code' => 'MATH',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminA)->post('/school/subjects', [
            'name' => 'Mathematics',
            'code' => 'MATH',
            'color' => '#6366f1',
        ]);

        $response->assertRedirect(route('school.subjects.index'));
        $this->assertDatabaseHas('subjects', ['code' => 'MATH', 'school_id' => $this->schoolA->id]);

        // A duplicate within the same school must be rejected.
        $dup = $this->actingAs($this->adminA)->post('/school/subjects', [
            'name' => 'Maths Again',
            'code' => 'MATH',
            'color' => '#000000',
        ]);
        $dup->assertSessionHasErrors('code');
    }

    public function test_migrate_fresh_route_is_removed(): void
    {
        $this->get('/migrate-fresh')->assertNotFound();
    }
}
