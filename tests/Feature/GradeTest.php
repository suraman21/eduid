<?php

namespace Tests\Feature;

use App\Models\ClassSubject;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GradeTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private User $teacher;
    private SchoolClass $class;
    private Subject $subject;
    private Term $term;

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
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('password'), 'role' => User::ROLE_SCHOOL_ADMIN,
            'school_id' => $this->school->id, 'is_active' => true,
        ]);

        $this->teacher = User::create([
            'name' => 'Teacher', 'email' => 'teacher@test.com',
            'password' => Hash::make('password'), 'role' => User::ROLE_TEACHER,
            'school_id' => $this->school->id, 'is_active' => true,
        ]);

        $this->class = SchoolClass::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'grade' => 'Grade 9', 'section' => 'A',
            'academic_year' => '2018 E.C.', 'is_active' => true,
        ]);

        $this->subject = Subject::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'name' => 'Mathematics', 'code' => 'MATH', 'is_active' => true,
        ]);

        ClassSubject::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id,
            'class_id'  => $this->class->id,
            'subject_id'=> $this->subject->id,
        ]);

        $this->term = Term::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'name' => 'Term 1',
            'academic_year' => '2018 E.C.', 'start_date' => '2026-01-01',
            'end_date' => '2026-04-30', 'order' => 1, 'is_current' => true,
        ]);
    }

    private function makeStudent(string $first): Student
    {
        return Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA' . strtoupper($first),
            'first_name' => $first, 'last_name' => 'Test', 'gender' => 'male',
            'status' => 'active', 'class_id' => $this->class->id, 'enrollment_date' => today(),
        ]);
    }

    private function gradePayload(array $grades): array
    {
        return [
            'class_id'        => $this->class->id,
            'subject_id'      => $this->subject->id,
            'term_id'         => $this->term->id,
            'assessment_type' => 'ca',
            'grades'          => $grades,
        ];
    }

    public function test_teacher_can_save_grades(): void
    {
        $s1 = $this->makeStudent('Abebe');
        $s2 = $this->makeStudent('Bekele');

        $response = $this->actingAs($this->teacher)->post('/school/grades/save', $this->gradePayload([
            ['student_id' => $s1->id, 'score' => 85, 'max_score' => 100],
            ['student_id' => $s2->id, 'score' => 72, 'max_score' => 100],
        ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('student_grades', ['student_id' => $s1->id, 'score' => 85, 'status' => 'draft']);
        $this->assertDatabaseHas('student_grades', ['student_id' => $s2->id, 'score' => 72, 'status' => 'draft']);
    }

    public function test_saving_grades_twice_updates_not_duplicates(): void
    {
        $s1 = $this->makeStudent('Abebe');

        $this->actingAs($this->teacher)->post('/school/grades/save', $this->gradePayload([
            ['student_id' => $s1->id, 'score' => 80, 'max_score' => 100],
        ]));

        $this->actingAs($this->teacher)->post('/school/grades/save', $this->gradePayload([
            ['student_id' => $s1->id, 'score' => 90, 'max_score' => 100],
        ]));

        $this->assertSame(1, StudentGrade::withoutGlobalScope('school')->count());
        $this->assertDatabaseHas('student_grades', ['student_id' => $s1->id, 'score' => '90.00']);
        $this->assertDatabaseMissing('student_grades', ['student_id' => $s1->id, 'score' => '80.00']);
    }

    public function test_admin_can_publish_grades(): void
    {
        $s1 = $this->makeStudent('Abebe');

        $this->actingAs($this->teacher)->post('/school/grades/save', $this->gradePayload([
            ['student_id' => $s1->id, 'score' => 88, 'max_score' => 100],
        ]));

        $this->actingAs($this->admin)->post('/school/grades/publish', [
            'class_id'        => $this->class->id,
            'subject_id'      => $this->subject->id,
            'term_id'         => $this->term->id,
            'assessment_type' => 'ca',
        ])->assertRedirect();

        $this->assertDatabaseHas('student_grades', ['student_id' => $s1->id, 'status' => 'published']);
    }

    public function test_teacher_cannot_publish_grades(): void
    {
        $s1 = $this->makeStudent('Abebe');

        $this->actingAs($this->teacher)->post('/school/grades/save', $this->gradePayload([
            ['student_id' => $s1->id, 'score' => 75, 'max_score' => 100],
        ]));

        $this->actingAs($this->teacher)->post('/school/grades/publish', [
            'class_id'        => $this->class->id,
            'subject_id'      => $this->subject->id,
            'term_id'         => $this->term->id,
            'assessment_type' => 'ca',
        ])->assertForbidden();
    }

    public function test_published_grades_cannot_be_overwritten(): void
    {
        $s1 = $this->makeStudent('Abebe');

        $this->actingAs($this->teacher)->post('/school/grades/save', $this->gradePayload([
            ['student_id' => $s1->id, 'score' => 80, 'max_score' => 100],
        ]));

        $this->actingAs($this->admin)->post('/school/grades/publish', [
            'class_id' => $this->class->id, 'subject_id' => $this->subject->id,
            'term_id' => $this->term->id, 'assessment_type' => 'ca',
        ]);

        // Attempt to overwrite published grade
        $this->actingAs($this->teacher)->post('/school/grades/save', $this->gradePayload([
            ['student_id' => $s1->id, 'score' => 60, 'max_score' => 100],
        ]))->assertSessionHasErrors('grades');

        // Score must remain 80
        $this->assertDatabaseMissing('student_grades', ['student_id' => $s1->id, 'score' => '60.00']);
    }

    public function test_cannot_save_grade_for_another_schools_student(): void
    {
        $otherSchool = School::create([
            'name' => 'Beta', 'code' => 'BETA', 'is_active' => true, 'plan_tier' => 'premium',
            'max_students' => 100, 'max_teachers' => 10, 'hmac_secret' => 'x',
            'academic_year' => '2018 E.C.', 'subscription_status' => 'active',
        ]);
        $foreign = Student::withoutGlobalScope('school')->create([
            'school_id' => $otherSchool->id, 'uid' => 'BETAX', 'first_name' => 'Foreign',
            'last_name' => 'Kid', 'gender' => 'male', 'status' => 'active', 'enrollment_date' => today(),
        ]);

        $this->actingAs($this->teacher)->post('/school/grades/save', $this->gradePayload([
            ['student_id' => $foreign->id, 'score' => 90, 'max_score' => 100],
        ]))->assertSessionHasErrors('grades.0.student_id');
    }
}
