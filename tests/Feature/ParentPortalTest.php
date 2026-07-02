<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Guardian;
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

/**
 * Parent accounts: admin creates/revokes app logins for guardians; the
 * parent API exposes ONLY the caller's own children, only published grades,
 * is gated on the 'parent_app' module, and parent tokens cannot reach
 * teacher endpoints. Parents are mobile-only (web login refused).
 */
class ParentPortalTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private Guardian $guardian;
    private User $parent;
    private Student $child;
    private Student $otherChild;

    protected function setUp(): void
    {
        parent::setUp();

        // Premium tier — includes 'parent_app' per the seeded matrix.
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

        $class = SchoolClass::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'grade' => 'Grade 3', 'section' => 'A',
            'academic_year' => '2018 E.C.', 'is_active' => true,
        ]);

        $this->child = Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA-2018-000001',
            'first_name' => 'Sara', 'last_name' => 'Kebede', 'gender' => 'female',
            'status' => 'active', 'enrollment_date' => today(), 'class_id' => $class->id,
        ]);
        $this->otherChild = Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA-2018-000002',
            'first_name' => 'Abel', 'last_name' => 'Tesfaye', 'gender' => 'male',
            'status' => 'active', 'enrollment_date' => today(), 'class_id' => $class->id,
        ]);

        $this->guardian = Guardian::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'full_name' => 'Kebede Alemu',
            'relationship' => 'father', 'phone' => '+251911000000',
        ]);
        $this->guardian->students()->attach($this->child->id, ['is_primary' => true]);

        $this->parent = User::create([
            'name' => 'Kebede Alemu', 'email' => 'kebede@test.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_PARENT, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->guardian->update(['user_id' => $this->parent->id]);
    }

    // ── Admin creates / revokes logins ───────────────────────────

    public function test_admin_creates_a_parent_login_with_one_time_password(): void
    {
        $fresh = Guardian::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'full_name' => 'Almaz Haile',
            'relationship' => 'mother',
        ]);

        $this->actingAs($this->admin)
            ->post("/school/guardians/{$fresh->id}/account", ['email' => 'almaz@test.com'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh->refresh();
        $this->assertNotNull($fresh->user_id);
        $this->assertSame('parent', $fresh->user->role);
        $this->assertStringContainsString('Temporary password:', session('success'));
    }

    public function test_admin_revokes_app_access(): void
    {
        $this->actingAs($this->admin)
            ->delete("/school/guardians/{$this->guardian->id}/account")
            ->assertRedirect();

        $this->assertFalse((bool) $this->parent->fresh()->can_login);
        $this->assertFalse((bool) $this->parent->fresh()->is_active);
    }

    public function test_teacher_cannot_manage_parent_accounts(): void
    {
        $teacher = User::create([
            'name' => 'T', 'email' => 't@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_TEACHER, 'school_id' => $this->school->id, 'is_active' => true,
        ]);

        $this->actingAs($teacher)
            ->post("/school/guardians/{$this->guardian->id}/account", ['email' => 'x@test.com'])
            ->assertForbidden();
    }

    // ── Parent API ───────────────────────────────────────────────

    public function test_parent_sees_only_their_own_children(): void
    {
        $res = $this->actingAs($this->parent, 'sanctum')
            ->getJson('/api/v1/parent/children')
            ->assertOk()
            ->json();

        $this->assertCount(1, $res['children']);
        $this->assertSame('Sara Kebede', $res['children'][0]['full_name']);
        $this->assertSame('Grade 3 - A', $res['children'][0]['class_name']);
    }

    public function test_parent_cannot_read_an_unlinked_students_data(): void
    {
        $this->actingAs($this->parent, 'sanctum')
            ->getJson("/api/v1/parent/children/{$this->otherChild->id}/attendance")
            ->assertNotFound();

        $this->actingAs($this->parent, 'sanctum')
            ->getJson("/api/v1/parent/children/{$this->otherChild->id}/grades")
            ->assertNotFound();
    }

    public function test_attendance_endpoint_returns_summary_and_records(): void
    {
        $session = AttendanceSession::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'class_id' => $this->child->class_id,
            'teacher_id' => $this->admin->id, 'session_date' => today(),
            'session_type' => 'morning',
        ]);
        AttendanceRecord::create([
            'session_id' => $session->id, 'student_id' => $this->child->id, 'status' => 'late',
        ]);

        $res = $this->actingAs($this->parent, 'sanctum')
            ->getJson("/api/v1/parent/children/{$this->child->id}/attendance")
            ->assertOk()->json();

        $this->assertSame(1, $res['month_summary']['late']);
        $this->assertSame('late', $res['records'][0]['status']);
    }

    public function test_grades_endpoint_exposes_published_rows_only(): void
    {
        $subject = Subject::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'name' => 'English', 'code' => 'ENG', 'is_active' => true,
        ]);
        $term = Term::create([
            'school_id' => $this->school->id, 'academic_year' => '2018 E.C.',
            'name' => 'Term 1', 'start_date' => today()->subMonth(),
            'end_date' => today()->addMonth(), 'is_current' => true,
        ]);

        foreach ([['published', 80], ['draft', 95]] as [$status, $score]) {
            StudentGrade::withoutGlobalScope('school')->create([
                'school_id' => $this->school->id, 'student_id' => $this->child->id,
                'subject_id' => $subject->id, 'term_id' => $term->id,
                'class_id' => $this->child->class_id,
                'assessment_type' => $status === 'published' ? 'final' : 'midterm',
                'score' => $score, 'max_score' => 100,
                'entered_by' => $this->admin->id, 'status' => $status,
            ]);
        }

        $res = $this->actingAs($this->parent, 'sanctum')
            ->getJson("/api/v1/parent/children/{$this->child->id}/grades")
            ->assertOk()->json();

        $assessments = $res['terms'][0]['subjects'][0]['assessments'];
        $this->assertCount(1, $assessments);           // the draft is invisible
        $this->assertSame('final', $assessments[0]['type']);
        $this->assertEquals(80, $res['terms'][0]['subjects'][0]['total_pct']);
    }

    // ── Gates ────────────────────────────────────────────────────

    public function test_parent_token_cannot_reach_teacher_endpoints(): void
    {
        $this->actingAs($this->parent, 'sanctum')
            ->getJson('/api/v1/classes')->assertForbidden();
        $this->actingAs($this->parent, 'sanctum')
            ->postJson('/api/v1/attendance/sync', ['sessions' => []])->assertForbidden();
    }

    public function test_teacher_token_cannot_reach_parent_endpoints(): void
    {
        $teacher = User::create([
            'name' => 'T2', 'email' => 't2@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_TEACHER, 'school_id' => $this->school->id, 'is_active' => true,
        ]);

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/v1/parent/children')->assertForbidden();
    }

    public function test_parent_app_module_gate(): void
    {
        // Downgrade the school to starter (no parent_app module).
        $this->school->update(['plan_tier' => 'starter']);
        $this->school->flushFeatureCache();

        $this->actingAs($this->parent, 'sanctum')
            ->getJson('/api/v1/parent/children')->assertForbidden();
    }

    public function test_parents_cannot_use_the_web_portal(): void
    {
        $this->post('/login', [
            'school_code' => 'ALPHA',
            'email'       => 'kebede@test.com',
            'password'    => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
