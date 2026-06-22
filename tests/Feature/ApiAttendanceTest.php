<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\QrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private User $teacher;
    private SchoolClass $classA; // teacher assigned
    private SchoolClass $classB; // teacher NOT assigned

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Alpha', 'code' => 'ALPHA', 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 1000, 'max_teachers' => 100,
            'hmac_secret' => 'secret-alpha', 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@a.com', 'password' => Hash::make('x'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->teacher = User::create([
            'name' => 'Teacher', 'email' => 'teacher@a.com', 'password' => Hash::make('x'),
            'role' => User::ROLE_TEACHER, 'school_id' => $this->school->id, 'is_active' => true,
        ]);

        $this->classA = $this->makeClass('Grade 9', 'A');
        $this->classB = $this->makeClass('Grade 10', 'B');

        DB::table('teacher_classes')->insert([
            'user_id' => $this->teacher->id, 'class_id' => $this->classA->id,
        ]);
    }

    private function makeClass(string $grade, string $section): SchoolClass
    {
        return SchoolClass::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'grade' => $grade, 'section' => $section,
            'academic_year' => '2018 E.C.', 'is_active' => true,
        ]);
    }

    private function makeStudent(SchoolClass $class, string $first): Student
    {
        $student = Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id,
            'uid' => 'ALPHA-' . strtoupper($first),
            'first_name' => $first, 'last_name' => 'Test', 'gender' => 'male',
            'status' => 'active', 'class_id' => $class->id, 'enrollment_date' => today(),
        ]);
        app(QrTokenService::class)->generate($student, $this->school);
        return $student->fresh();
    }

    // ── Auth ────────────────────────────────────────────────────────────────

    public function test_endpoints_require_a_token(): void
    {
        $this->getJson('/api/v1/classes')->assertStatus(401);
        $this->postJson('/api/v1/attendance/verify-qr', [])->assertStatus(401);
        $this->postJson('/api/v1/attendance/sync', [])->assertStatus(401);
    }

    // ── Classes + roster ─────────────────────────────────────────────────────

    public function test_teacher_only_sees_assigned_classes(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/v1/classes')
            ->assertOk()
            ->assertJsonCount(1, 'classes')
            ->assertJsonFragment(['id' => $this->classA->id]);
    }

    public function test_admin_sees_all_classes(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/classes')
            ->assertOk()
            ->assertJsonCount(2, 'classes');
    }

    public function test_teacher_cannot_fetch_roster_of_unassigned_class(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->getJson("/api/v1/classes/{$this->classB->id}/students")
            ->assertStatus(403);
    }

    public function test_roster_returns_active_students_with_tokens(): void
    {
        $s = $this->makeStudent($this->classA, 'Abebe');
        Sanctum::actingAs($this->teacher);

        $this->getJson("/api/v1/classes/{$this->classA->id}/students")
            ->assertOk()
            ->assertJsonCount(1, 'students')
            ->assertJsonFragment(['uid' => $s->uid, 'qr_token' => $s->qr_token]);
    }

    // ── verify-qr ─────────────────────────────────────────────────────────────

    public function test_verify_qr_accepts_a_valid_payload(): void
    {
        $s = $this->makeStudent($this->classA, 'Bekele');
        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/v1/attendance/verify-qr', [
            'qr_data' => $s->uid . '|' . $s->qr_token,
        ])->assertOk()->assertJson([
            'valid' => true,
            'student' => ['uid' => $s->uid],
        ]);
    }

    public function test_verify_qr_rejects_a_forged_token(): void
    {
        $s = $this->makeStudent($this->classA, 'Caleb');
        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/v1/attendance/verify-qr', [
            'qr_data' => $s->uid . '|deadbeef',
        ])->assertOk()->assertJson(['valid' => false]);
    }

    public function test_verify_qr_rejects_cross_school_uid(): void
    {
        // A student from another school must not verify against this school.
        $other = School::create([
            'name' => 'Beta', 'code' => 'BETA', 'is_active' => true, 'plan_tier' => 'premium',
            'max_students' => 100, 'max_teachers' => 10, 'hmac_secret' => 'secret-beta',
            'academic_year' => '2018 E.C.', 'subscription_status' => 'active',
        ]);
        $foreign = Student::withoutGlobalScope('school')->create([
            'school_id' => $other->id, 'uid' => 'BETA-X', 'first_name' => 'X', 'last_name' => 'Y',
            'gender' => 'male', 'status' => 'active', 'enrollment_date' => today(),
        ]);
        app(QrTokenService::class)->generate($foreign, $other);
        $foreign->refresh();

        Sanctum::actingAs($this->teacher);
        $this->postJson('/api/v1/attendance/verify-qr', [
            'qr_data' => $foreign->uid . '|' . $foreign->qr_token,
        ])->assertOk()->assertJson(['valid' => false]);
    }

    // ── sync ─────────────────────────────────────────────────────────────────

    public function test_sync_creates_session_and_records(): void
    {
        $s1 = $this->makeStudent($this->classA, 'Dawit');
        $s2 = $this->makeStudent($this->classA, 'Elsa');
        Sanctum::actingAs($this->teacher);

        $payload = ['sessions' => [[
            'class_id' => $this->classA->id,
            'session_date' => today()->toDateString(),
            'session_type' => 'morning',
            'device_id' => 'phone-1',
            'idempotency_key' => 'key-1',
            'records' => [
                ['student_id' => $s1->id, 'status' => 'present', 'scanned_at' => now()->toIso8601String()],
                ['student_id' => $s2->id, 'status' => 'absent'],
            ],
        ]]];

        $this->postJson('/api/v1/attendance/sync', $payload)
            ->assertOk()
            ->assertJson(['synced' => 1, 'skipped' => 0, 'failed' => 0]);

        $this->assertDatabaseHas('attendance_sessions', [
            'class_id' => $this->classA->id, 'idempotency_key' => 'key-1',
            'synced_from_app' => true, 'method' => 'qr_scan',
        ]);
        $this->assertDatabaseHas('attendance_records', ['student_id' => $s1->id, 'status' => 'present']);
        $this->assertDatabaseHas('attendance_records', ['student_id' => $s2->id, 'status' => 'absent']);
    }

    public function test_sync_is_idempotent_on_resend(): void
    {
        $s1 = $this->makeStudent($this->classA, 'Fasil');
        Sanctum::actingAs($this->teacher);

        $payload = ['sessions' => [[
            'class_id' => $this->classA->id,
            'session_date' => today()->toDateString(),
            'session_type' => 'morning',
            'idempotency_key' => 'key-dupe',
            'records' => [['student_id' => $s1->id, 'status' => 'present']],
        ]]];

        $this->postJson('/api/v1/attendance/sync', $payload)->assertOk()->assertJson(['synced' => 1]);
        $this->postJson('/api/v1/attendance/sync', $payload)->assertOk()->assertJson(['synced' => 0, 'skipped' => 1]);

        $this->assertSame(1, AttendanceSession::withoutGlobalScope('school')->where('idempotency_key', 'key-dupe')->count());
    }

    public function test_sync_rejects_unassigned_class_for_teacher(): void
    {
        $s = $this->makeStudent($this->classB, 'Genet');
        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/v1/attendance/sync', ['sessions' => [[
            'class_id' => $this->classB->id,
            'session_date' => today()->toDateString(),
            'session_type' => 'morning',
            'idempotency_key' => 'key-bad-class',
            'records' => [['student_id' => $s->id, 'status' => 'present']],
        ]]])->assertOk()->assertJson(['failed' => 1]);

        $this->assertDatabaseMissing('attendance_sessions', ['idempotency_key' => 'key-bad-class']);
    }
}
