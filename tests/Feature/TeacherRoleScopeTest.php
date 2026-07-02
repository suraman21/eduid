<?php

namespace Tests\Feature;

use App\Models\ClassSubject;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\TeacherAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The teacher web experience: teachers must get a teacher dashboard, must be
 * blocked from every admin-only page, and may only touch classes assigned to
 * them. Plus the TeacherAssignmentService contract (class+subject matrix,
 * homeroom-of-class, attendance rights kept in sync).
 */
class TeacherRoleScopeTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private User $teacher;
    private SchoolClass $classA;
    private SchoolClass $classB;
    private Subject $english;
    private Subject $maths;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Alpha', 'code' => 'ALPHA', 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 100, 'max_teachers' => 10,
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
        $this->classA = SchoolClass::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'grade' => 'Grade 1', 'section' => 'A',
            'academic_year' => '2018 E.C.', 'is_active' => true,
        ]);
        $this->classB = SchoolClass::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'grade' => 'Grade 2', 'section' => 'B',
            'academic_year' => '2018 E.C.', 'is_active' => true,
        ]);
        $this->english = Subject::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'name' => 'English', 'code' => 'ENG', 'is_active' => true,
        ]);
        $this->maths = Subject::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'name' => 'Maths', 'code' => 'MAT', 'is_active' => true,
        ]);
    }

    // ── Route guards ─────────────────────────────────────────────

    public function test_teacher_gets_teacher_dashboard_not_admin_one(): void
    {
        $this->actingAs($this->teacher)->get('/school/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('School/TeacherDashboard', false));
    }

    public function test_admin_still_gets_admin_dashboard(): void
    {
        $this->actingAs($this->admin)->get('/school/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('School/Dashboard', false));
    }

    public function test_teacher_is_blocked_from_admin_pages(): void
    {
        foreach ([
            '/school/students',
            '/school/students/create',
            '/school/classes',
            '/school/subjects',
            '/school/staff',
            '/school/card-orders',
            '/school/photo-sessions',
            '/school/report-cards',
            '/school/students/import',
        ] as $url) {
            $this->actingAs($this->teacher)->get($url)->assertForbidden();
        }
    }

    public function test_teacher_cannot_create_students(): void
    {
        $this->actingAs($this->teacher)->post('/school/students', [
            'first_name' => 'X', 'last_name' => 'Y', 'gender' => 'male',
        ])->assertForbidden();
    }

    public function test_teacher_can_reach_attendance_and_grades(): void
    {
        $this->actingAs($this->teacher)->get('/school/attendance')->assertOk();
        $this->actingAs($this->teacher)->get('/school/grades')->assertOk();
    }

    // ── Attendance class scoping (web) ───────────────────────────

    public function test_teacher_cannot_record_attendance_for_unassigned_class(): void
    {
        Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA001',
            'first_name' => 'Abebe', 'last_name' => 'Test', 'gender' => 'male',
            'status' => 'active', 'enrollment_date' => today(),
            'class_id' => $this->classB->id,
        ]);

        $this->actingAs($this->teacher)->post('/school/attendance', [
            'class_id'     => $this->classB->id,
            'session_date' => today()->toDateString(),
            'session_type' => 'morning',
            'records'      => [
                ['student_id' => Student::withoutGlobalScope('school')->first()->id, 'status' => 'present'],
            ],
        ])->assertForbidden();
    }

    public function test_teacher_can_record_attendance_for_assigned_class(): void
    {
        app(TeacherAssignmentService::class)->sync($this->teacher, [
            ['class_id' => $this->classA->id, 'subject_id' => null],
        ], []);

        $student = Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA002',
            'first_name' => 'Sara', 'last_name' => 'Test', 'gender' => 'female',
            'status' => 'active', 'enrollment_date' => today(),
            'class_id' => $this->classA->id,
        ]);

        $this->actingAs($this->teacher)->post('/school/attendance', [
            'class_id'     => $this->classA->id,
            'session_date' => today()->toDateString(),
            'session_type' => 'morning',
            'records'      => [['student_id' => $student->id, 'status' => 'present']],
        ])->assertRedirect();

        $this->assertDatabaseHas('attendance_sessions', [
            'class_id' => $this->classA->id, 'teacher_id' => $this->teacher->id,
        ]);
    }

    // ── TeacherAssignmentService contract ────────────────────────

    public function test_assignment_sync_populates_all_four_stores(): void
    {
        app(TeacherAssignmentService::class)->sync($this->teacher, [
            ['class_id' => $this->classA->id, 'subject_id' => $this->english->id],
            ['class_id' => $this->classB->id, 'subject_id' => $this->maths->id],
        ], [$this->classB->id]);

        // class_subjects rows claimed
        $this->assertDatabaseHas('class_subjects', [
            'class_id' => $this->classA->id, 'subject_id' => $this->english->id,
            'teacher_id' => $this->teacher->id,
        ]);
        // homeroom on the class
        $this->assertSame(
            $this->teacher->id,
            SchoolClass::withoutGlobalScope('school')->find($this->classB->id)->homeroom_teacher_id
        );
        // attendance rights for both classes
        $this->assertEqualsCanonicalizing(
            [$this->classA->id, $this->classB->id],
            $this->teacher->assignedClasses()->pluck('classes.id')->all()
        );
        // derived flag
        $this->assertTrue($this->teacher->fresh()->is_homeroom);
    }

    public function test_assignment_sync_releases_dropped_slots(): void
    {
        $service = app(TeacherAssignmentService::class);

        $service->sync($this->teacher, [
            ['class_id' => $this->classA->id, 'subject_id' => $this->english->id],
        ], [$this->classA->id]);

        // Drop everything.
        $service->sync($this->teacher, [], []);

        $this->assertNull(
            ClassSubject::withoutGlobalScope('school')
                ->where('class_id', $this->classA->id)
                ->where('subject_id', $this->english->id)
                ->value('teacher_id')
        );
        $this->assertNull(
            SchoolClass::withoutGlobalScope('school')->find($this->classA->id)->homeroom_teacher_id
        );
        $this->assertSame(0, $this->teacher->assignedClasses()->count());
        $this->assertFalse($this->teacher->fresh()->is_homeroom);
    }

    public function test_assignment_rejects_other_schools_class(): void
    {
        $other = School::create([
            'name' => 'Beta', 'code' => 'BETA', 'is_active' => true,
            'plan_tier' => 'starter', 'max_students' => 50, 'max_teachers' => 5,
            'hmac_secret' => 'secret2', 'academic_year' => '2018 E.C.',
        ]);
        $foreignClass = SchoolClass::withoutGlobalScope('school')->create([
            'school_id' => $other->id, 'grade' => 'Grade 9', 'section' => 'Z',
            'academic_year' => '2018 E.C.', 'is_active' => true,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(TeacherAssignmentService::class)->sync($this->teacher, [
            ['class_id' => $foreignClass->id, 'subject_id' => $this->english->id],
        ], []);
    }

    public function test_staff_form_creates_teacher_with_assignments(): void
    {
        $this->actingAs($this->admin)->post('/school/staff', [
            'name'     => 'New Teacher',
            'email'    => 'new.teacher@test.com',
            'password' => 'password123',
            'role'     => 'teacher',
            'assignments' => [
                ['class_id' => $this->classA->id, 'subject_id' => $this->english->id],
            ],
            'homeroom_class_ids' => [$this->classA->id],
        ])->assertRedirect('/school/staff');

        $created = User::withoutGlobalScope('school')
            ->where('email', 'new.teacher@test.com')->firstOrFail();

        $this->assertDatabaseHas('class_subjects', [
            'class_id' => $this->classA->id, 'subject_id' => $this->english->id,
            'teacher_id' => $created->id,
        ]);
        $this->assertSame(
            $created->id,
            SchoolClass::withoutGlobalScope('school')->find($this->classA->id)->homeroom_teacher_id
        );
    }

    public function test_grade_entry_blocked_for_slot_owned_by_another_teacher(): void
    {
        $service = app(TeacherAssignmentService::class);

        // Other teacher owns 1A English.
        $other = User::create([
            'name' => 'Other', 'email' => 'other@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_TEACHER, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $service->sync($other, [
            ['class_id' => $this->classA->id, 'subject_id' => $this->english->id],
        ], []);

        // Our teacher has class access (attendance-only) but not the slot.
        $service->sync($this->teacher, [
            ['class_id' => $this->classA->id, 'subject_id' => null],
        ], []);

        $term = \App\Models\Term::create([
            'school_id' => $this->school->id, 'academic_year' => '2018 E.C.',
            'name' => 'Term 1', 'start_date' => today()->subMonth(),
            'end_date' => today()->addMonth(), 'is_current' => true,
        ]);

        $this->actingAs($this->teacher)->get('/school/grades/enter?' . http_build_query([
            'class_id'        => $this->classA->id,
            'subject_id'      => $this->english->id,
            'term_id'         => $term->id,
            'assessment_type' => 'final',
        ]))->assertForbidden();

        // The slot owner may enter.
        $this->actingAs($other)->get('/school/grades/enter?' . http_build_query([
            'class_id'        => $this->classA->id,
            'subject_id'      => $this->english->id,
            'term_id'         => $term->id,
            'assessment_type' => 'final',
        ]))->assertOk();
    }
}
