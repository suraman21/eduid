<?php

namespace Tests\Feature;

use App\Models\AssessmentConfig;
use App\Models\ClassSubject;
use App\Models\GradingScale;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GradeApiTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $teacher;
    private SchoolClass $class;
    private Term $term;
    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Test', 'code' => 'TST', 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 100, 'max_teachers' => 10,
            'hmac_secret' => 'x', 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);

        $this->teacher = User::create([
            'name' => 'T', 'email' => 't@t.com', 'password' => Hash::make('pw'),
            'role' => 'teacher', 'school_id' => $this->school->id, 'is_active' => true,
        ]);

        $this->class = SchoolClass::create([
            'school_id' => $this->school->id, 'grade' => '5', 'section' => 'A',
            'is_active' => true, 'academic_year' => '2018 E.C.',
        ]);

        $this->class->teachers()->attach($this->teacher->id);

        $this->term = Term::create([
            'school_id' => $this->school->id, 'name' => 'Term 1',
            'academic_year' => '2018 E.C.', 'order' => 1, 'is_current' => true,
            'start_date' => '2025-09-01', 'end_date' => '2025-12-31',
        ]);

        $this->subject = Subject::create([
            'school_id' => $this->school->id, 'name' => 'Math', 'code' => 'MAT',
        ]);

        ClassSubject::create([
            'school_id' => $this->school->id, 'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
        ]);

        GradingScale::create([
            'school_id' => $this->school->id, 'label' => 'A',
            'min_score' => 90, 'max_score' => 100, 'sort_order' => 1,
        ]);
        GradingScale::create([
            'school_id' => $this->school->id, 'label' => 'B',
            'min_score' => 80, 'max_score' => 89.99, 'sort_order' => 2,
        ]);
    }

    public function test_grades_api_returns_student_grades(): void
    {
        $student = Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'TST12345',
            'first_name' => 'Abebe', 'last_name' => 'Test',
            'gender' => 'male', 'status' => 'active',
            'enrollment_date' => today(), 'class_id' => $this->class->id,
        ]);

        foreach (['ca', 'midterm', 'final'] as $type) {
            StudentGrade::create([
                'school_id' => $this->school->id, 'student_id' => $student->id,
                'subject_id' => $this->subject->id, 'term_id' => $this->term->id,
                'class_id' => $this->class->id, 'assessment_type' => $type,
                'score' => 90, 'max_score' => 100, 'status' => 'published',
                'entered_by' => $this->teacher->id,
            ]);
        }

        Sanctum::actingAs($this->teacher);

        $response = $this->getJson("/api/v1/classes/{$this->class->id}/grades?term_id={$this->term->id}");
        $response->assertOk();
        $response->assertJsonPath('students.0.full_name', 'Abebe Test');
        $this->assertEquals(90, $response->json('students.0.subjects.0.total'));
        $response->assertJsonPath('students.0.subjects.0.letter', 'A');
        $response->assertJsonPath('students.0.rank', 1);
    }

    public function test_grades_api_403_for_unassigned_class(): void
    {
        $otherClass = SchoolClass::create([
            'school_id' => $this->school->id, 'grade' => '6', 'section' => 'B',
            'is_active' => true, 'academic_year' => '2018 E.C.',
        ]);

        Sanctum::actingAs($this->teacher);

        $this->getJson("/api/v1/classes/{$otherClass->id}/grades")
            ->assertForbidden();
    }
}
