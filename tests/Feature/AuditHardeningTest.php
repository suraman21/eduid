<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regressions for the go-live audit fixes:
 *  1. cross-tenant class_id can never validate on student writes
 *  2. teachers cannot fetch photos of students outside their classes
 *  3. attendance/grades reject students who are not in the posted class
 *  4. class/subject creation is blocked on an expired subscription
 */
class AuditHardeningTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private School $other;
    private User $admin;
    private User $teacher;
    private SchoolClass $classA;
    private SchoolClass $classB;
    private SchoolClass $foreignClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Alpha', 'code' => 'ALPHA', 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 100, 'max_teachers' => 10,
            'hmac_secret' => 's1', 'academic_year' => '2018 E.C.', 'subscription_status' => 'active',
        ]);
        $this->other = School::create([
            'name' => 'Beta', 'code' => 'BETA', 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 100, 'max_teachers' => 10,
            'hmac_secret' => 's2', 'academic_year' => '2018 E.C.', 'subscription_status' => 'active',
        ]);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'a@a.test', 'password' => Hash::make('password'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->teacher = User::create([
            'name' => 'T', 'email' => 't@a.test', 'password' => Hash::make('password'),
            'role' => User::ROLE_TEACHER, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->classA = SchoolClass::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'grade' => 'G1', 'section' => 'A',
            'academic_year' => '2018 E.C.', 'is_active' => true,
        ]);
        $this->classB = SchoolClass::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'grade' => 'G2', 'section' => 'B',
            'academic_year' => '2018 E.C.', 'is_active' => true,
        ]);
        $this->foreignClass = SchoolClass::withoutGlobalScope('school')->create([
            'school_id' => $this->other->id, 'grade' => 'GX', 'section' => 'X',
            'academic_year' => '2018 E.C.', 'is_active' => true,
        ]);
    }

    private function student(SchoolClass $class, string $uid): Student
    {
        return Student::withoutGlobalScope('school')->create([
            'school_id' => $class->school_id, 'uid' => $uid,
            'first_name' => 'S', 'last_name' => $uid, 'gender' => 'male',
            'status' => 'active', 'enrollment_date' => today(), 'class_id' => $class->id,
            'photo_path' => 'x/nonexistent.jpg',
        ]);
    }

    public function test_student_cannot_be_created_into_a_foreign_schools_class(): void
    {
        $this->actingAs($this->admin)->post('/school/students', [
            'first_name' => 'Evil', 'last_name' => 'Move', 'gender' => 'male',
            'class_id' => $this->foreignClass->id,
        ])->assertSessionHasErrors('class_id');

        $this->assertDatabaseMissing('students', ['first_name' => 'Evil']);
    }

    public function test_teacher_cannot_fetch_photo_of_student_outside_their_classes(): void
    {
        $mine  = $this->student($this->classA, 'A-1');
        $notMine = $this->student($this->classB, 'B-1');
        $this->teacher->assignedClasses()->attach($this->classA->id);

        // Not their class → 403 before any file lookup.
        $this->actingAs($this->teacher)->get("/school/students/{$notMine->id}/photo")
            ->assertForbidden();

        // Their own class → passes the guard (404 only because the file is fake).
        $this->actingAs($this->teacher)->get("/school/students/{$mine->id}/photo")
            ->assertNotFound();
    }

    public function test_attendance_rejects_students_not_in_the_posted_class(): void
    {
        $inB = $this->student($this->classB, 'B-2');

        $this->actingAs($this->admin)->post('/school/attendance', [
            'class_id'     => $this->classA->id,
            'session_date' => today()->toDateString(),
            'session_type' => 'morning',
            'records'      => [['student_id' => $inB->id, 'status' => 'present']],
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseCount('attendance_sessions', 0);
    }

    public function test_class_creation_blocked_when_subscription_expired(): void
    {
        $this->school->update(['subscription_status' => 'expired']);

        $this->actingAs($this->admin)->post('/school/classes', [
            'grade' => 'G9', 'section' => 'C', 'academic_year' => '2018 E.C.',
        ])->assertForbidden();

        $this->actingAs($this->admin)->post('/school/subjects', [
            'name' => 'Physics', 'code' => 'PHY',
        ])->assertForbidden();
    }
}
