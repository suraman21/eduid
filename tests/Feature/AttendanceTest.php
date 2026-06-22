<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private User $teacher;
    private User $staff;
    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Alpha Academy',
            'code' => 'ALPHA',
            'is_active' => true,
            'plan_tier' => 'premium',
            'max_students' => 1000,
            'max_teachers' => 100,
            'hmac_secret' => 'secret',
            'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);

        $this->admin   = $this->makeUser(User::ROLE_SCHOOL_ADMIN, 'admin@test.com');
        $this->teacher = $this->makeUser(User::ROLE_TEACHER, 'teacher@test.com');
        $this->staff   = $this->makeUser('staff', 'staff@test.com');

        $this->class = SchoolClass::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id,
            'grade' => 'Grade 9',
            'section' => 'A',
            'academic_year' => '2018 E.C.',
            'is_active' => true,
        ]);
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'name' => $email,
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => $role,
            'school_id' => $this->school->id,
            'is_active' => true,
        ]);
    }

    private function makeStudent(string $first): Student
    {
        return Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id,
            'uid' => strtoupper('ALPHA' . $first),
            'first_name' => $first,
            'last_name' => 'Test',
            'gender' => 'male',
            'status' => 'active',
            'class_id' => $this->class->id,
            'enrollment_date' => today(),
        ]);
    }

    private function payload(array $records): array
    {
        return [
            'class_id'     => $this->class->id,
            'session_date' => today()->toDateString(),
            'session_type' => 'morning',
            'records'      => $records,
        ];
    }

    public function test_teacher_can_record_attendance(): void
    {
        $s1 = $this->makeStudent('Abebe');
        $s2 = $this->makeStudent('Bekele');

        $response = $this->actingAs($this->teacher)->post('/school/attendance', $this->payload([
            ['student_id' => $s1->id, 'status' => 'present'],
            ['student_id' => $s2->id, 'status' => 'absent', 'note' => 'Sick'],
        ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('attendance_sessions', [
            'school_id' => $this->school->id,
            'class_id'  => $this->class->id,
            'teacher_id' => $this->teacher->id,
            'method' => 'manual',
        ]);
        $this->assertDatabaseHas('attendance_records', ['student_id' => $s1->id, 'status' => 'present']);
        $this->assertDatabaseHas('attendance_records', ['student_id' => $s2->id, 'status' => 'absent', 'note' => 'Sick']);
    }

    public function test_resaving_same_slot_updates_not_duplicates(): void
    {
        $s1 = $this->makeStudent('Abebe');

        $this->actingAs($this->teacher)->post('/school/attendance', $this->payload([
            ['student_id' => $s1->id, 'status' => 'present'],
        ]));

        // Re-submit the same class/date/session with a changed status.
        $this->actingAs($this->teacher)->post('/school/attendance', $this->payload([
            ['student_id' => $s1->id, 'status' => 'late'],
        ]));

        // Exactly one session and one record must exist.
        $this->assertSame(1, AttendanceSession::withoutGlobalScope('school')->count());
        $this->assertDatabaseHas('attendance_records', ['student_id' => $s1->id, 'status' => 'late']);
        $this->assertDatabaseMissing('attendance_records', ['student_id' => $s1->id, 'status' => 'present']);
    }

    public function test_support_staff_cannot_record_attendance(): void
    {
        $s1 = $this->makeStudent('Abebe');

        $this->actingAs($this->staff)->post('/school/attendance', $this->payload([
            ['student_id' => $s1->id, 'status' => 'present'],
        ]))->assertForbidden();
    }

    public function test_cannot_record_for_another_schools_student(): void
    {
        $otherSchool = School::create([
            'name' => 'Beta', 'code' => 'BETA', 'is_active' => true, 'plan_tier' => 'premium',
            'max_students' => 100, 'max_teachers' => 10, 'hmac_secret' => 'x', 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);
        $foreign = Student::withoutGlobalScope('school')->create([
            'school_id' => $otherSchool->id, 'uid' => 'BETAX', 'first_name' => 'Foreign',
            'last_name' => 'Kid', 'gender' => 'male', 'status' => 'active', 'enrollment_date' => today(),
        ]);

        $this->actingAs($this->teacher)->post('/school/attendance', $this->payload([
            ['student_id' => $foreign->id, 'status' => 'present'],
        ]))->assertSessionHasErrors('records.0.student_id');
    }
}
