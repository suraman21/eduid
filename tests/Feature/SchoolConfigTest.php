<?php

namespace Tests\Feature;

use App\Models\PhotoSession;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TimetablePeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The school-configuration wave: settings page + attendance cadence,
 * bell-schedule overlap rejection, working-day-aware mobile schedule,
 * photo-less card-order guard, no-photo filter, and the mobile
 * photo-session API.
 */
class SchoolConfigTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private User $teacher;
    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Alpha', 'code' => 'ALPHA', 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 100, 'max_teachers' => 10,
            'hmac_secret' => 's', 'academic_year' => '2018 E.C.', 'subscription_status' => 'active',
        ]);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'a@a.t', 'password' => Hash::make('password'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->teacher = User::create([
            'name' => 'T', 'email' => 't@a.t', 'password' => Hash::make('password'),
            'role' => User::ROLE_TEACHER, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->class = SchoolClass::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'grade' => 'G1', 'section' => 'A',
            'academic_year' => '2018 E.C.', 'is_active' => true,
        ]);
    }

    // ── Settings page + cadence ──────────────────────────────────

    public function test_admin_can_set_once_daily_cadence_and_attendance_form_follows(): void
    {
        $this->actingAs($this->admin)
            ->patch('/school/settings', ['attendance_cadence' => 'once'])
            ->assertRedirect();

        $this->assertSame('once', $this->school->fresh()->attendance_cadence);
        $this->assertSame(['full_day'], $this->school->fresh()->sessionTypes());

        $this->actingAs($this->admin)->get('/school/attendance/create')
            ->assertInertia(fn ($p) => $p->where('sessionTypes', ['full_day']));
    }

    public function test_per_period_cadence_requires_the_subject_attendance_module(): void
    {
        // Premium seed matrix may or may not include it — force-remove.
        \App\Models\PlanFeature::where('feature_key', 'attendance_subject')->delete();
        $this->school->flushFeatureCache();

        $this->actingAs($this->admin)
            ->patch('/school/settings', ['attendance_cadence' => 'per_period'])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame('twice', $this->school->fresh()->attendance_cadence);
    }

    public function test_teacher_cannot_open_settings(): void
    {
        $this->actingAs($this->teacher)->get('/school/settings')->assertForbidden();
    }

    // ── Bell-schedule overlap (#9) ───────────────────────────────

    public function test_overlapping_period_is_rejected_with_named_clash(): void
    {
        TimetablePeriod::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'label' => 'Period 1',
            'start_time' => '08:00', 'end_time' => '08:40', 'sort_order' => 1,
        ]);

        // Overlaps 08:00–08:40 → rejected.
        $this->actingAs($this->admin)->post('/school/timetable/periods', [
            'label' => 'Break', 'start_time' => '08:20', 'end_time' => '09:00',
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertSame(1, TimetablePeriod::withoutGlobalScope('school')->count());

        // Back-to-back (starts exactly at the previous end) → allowed.
        $this->actingAs($this->admin)->post('/school/timetable/periods', [
            'label' => 'Period 2', 'start_time' => '08:40', 'end_time' => '09:20',
        ])->assertRedirect()->assertSessionMissing('error');
        $this->assertSame(2, TimetablePeriod::withoutGlobalScope('school')->count());
    }

    // ── Mobile schedule respects working days (#7) ───────────────

    public function test_mobile_week_excludes_non_working_days(): void
    {
        $this->school->update(['working_days' => [1, 2, 3, 4, 5]]); // Mon–Fri

        $res = $this->actingAs($this->teacher, 'sanctum')->getJson('/api/v1/schedule/week')
            ->assertOk()
            ->json();

        $days = collect($res['week'])->pluck('day')->all();
        $this->assertSame([1, 2, 3, 4, 5], $days);
        $this->assertNotContains(7, $days); // never Sunday
        $this->assertSame([1, 2, 3, 4, 5], $res['working_days']);
    }

    // ── Card order photo guard (#13) ─────────────────────────────

    public function test_card_order_blocked_when_a_student_has_no_photo(): void
    {
        $s = Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA-1', 'first_name' => 'No',
            'last_name' => 'Photo', 'gender' => 'male', 'status' => 'active',
            'enrollment_date' => today(), 'class_id' => $this->class->id,
        ]);

        $this->actingAs($this->admin)->post('/school/card-orders', [
            'order_type' => 'annual_renewal', 'student_ids' => [$s->id],
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseCount('card_orders', 0);
    }

    // ── Mobile photo-session API (#14) ───────────────────────────

    public function test_mobile_photo_session_flow_upload_and_advance(): void
    {
        $this->markTestSkipped('Photo Session feature disabled 2026-07 (routes commented out).');
        Storage::fake('local');

        $s1 = Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA-P1', 'first_name' => 'One',
            'last_name' => 'S', 'gender' => 'male', 'status' => 'active',
            'enrollment_date' => today(), 'class_id' => $this->class->id,
        ]);
        $s2 = Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA-P2', 'first_name' => 'Two',
            'last_name' => 'S', 'gender' => 'female', 'status' => 'active',
            'enrollment_date' => today(), 'class_id' => $this->class->id,
        ]);

        $session = PhotoSession::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'created_by' => $this->admin->id,
            'class_id' => $this->class->id, 'session_type' => 'app_capture',
            'status' => 'active', 'student_queue' => [$s1->id, $s2->id],
            'total_students' => 2, 'photos_captured' => 0, 'current_position' => 0,
        ]);

        // Active session is visible to the admin's app.
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/photo-sessions/active')
            ->assertOk()->assertJsonPath('session.id', $session->id)
            ->assertJsonPath('session.queue.0.is_current', true);

        // Upload for the current student advances the pointer.
        $this->actingAs($this->admin, 'sanctum')->postJson(
            "/api/v1/photo-sessions/{$session->id}/photo",
            ['photo' => UploadedFile::fake()->image('s1.jpg', 600, 800), 'student_id' => $s1->id]
        )->assertOk()->assertJsonPath('session.current_position', 1);

        $this->assertNotNull($s1->fresh()->photo_path);

        // Second upload completes the session.
        $this->actingAs($this->admin, 'sanctum')->postJson(
            "/api/v1/photo-sessions/{$session->id}/photo",
            ['photo' => UploadedFile::fake()->image('s2.jpg', 600, 800), 'student_id' => $s2->id]
        )->assertOk()->assertJsonPath('session.status', 'completed');
    }

    public function test_teacher_token_cannot_touch_photo_sessions(): void
    {
        $this->markTestSkipped('Photo Session feature disabled 2026-07 (routes commented out).');
        $this->actingAs($this->teacher, 'sanctum')->getJson('/api/v1/photo-sessions/active')
            ->assertForbidden();
    }

    // ── Duplicate-attendance signal for the app (#11) ────────────

    public function test_classes_api_reports_sessions_already_recorded_today(): void
    {
        $this->teacher->assignedClasses()->attach($this->class->id);

        \App\Models\AttendanceSession::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'class_id' => $this->class->id,
            'teacher_id' => $this->teacher->id, 'session_date' => today(), 'session_type' => 'morning',
        ]);

        $this->actingAs($this->teacher, 'sanctum')->getJson('/api/v1/classes')
            ->assertOk()
            ->assertJsonPath('classes.0.recorded_today.0', 'morning')
            ->assertJsonPath('session_types', ['morning', 'afternoon']);
    }
}
