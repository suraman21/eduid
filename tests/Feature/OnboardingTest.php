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

/**
 * Onboarding tour state + LIVE progress signals: persisted UI flags,
 * data-derived checklist completion, the "finished" celebration write, and
 * the guard that super admins / guests never receive the payload.
 */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;

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
            'name' => 'Admin', 'email' => 'admin@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
    }

    public function test_new_admin_gets_onboarding_with_unfinished_progress(): void
    {
        $this->actingAs($this->admin)->get('/school/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('onboarding.tour_seen', false)
                ->where('onboarding.finished_at', null)
                ->where('onboarding.progress.has_classes', false)
                ->where('onboarding.progress.has_students', false)
            );
    }

    public function test_progress_signals_reflect_real_data(): void
    {
        $class = SchoolClass::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'grade' => 'Grade 1', 'section' => 'A',
            'academic_year' => '2018 E.C.', 'is_active' => true,
        ]);
        Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA-001',
            'first_name' => 'Sara', 'last_name' => 'K', 'gender' => 'female',
            'status' => 'active', 'enrollment_date' => today(), 'class_id' => $class->id,
        ]);

        $this->actingAs($this->admin)->get('/school/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('onboarding.progress.has_classes', true)
                ->where('onboarding.progress.has_students', true)
                ->where('onboarding.progress.has_attendance', false)
            );
    }

    public function test_marking_tour_seen_persists(): void
    {
        $this->actingAs($this->admin)
            ->patch('/school/onboarding', ['tour_seen' => true])
            ->assertRedirect();

        $this->assertTrue($this->admin->fresh()->onboarding_state['tour_seen']);
    }

    public function test_finishing_writes_finished_at_once_and_stops_progress_queries(): void
    {
        $this->actingAs($this->admin)
            ->patch('/school/onboarding', ['finished' => true])
            ->assertRedirect();

        $first = $this->admin->fresh()->onboarding_state['finished_at'];
        $this->assertNotNull($first);

        // A second "finish" must NOT move the timestamp.
        $this->actingAs($this->admin)->patch('/school/onboarding', ['finished' => true]);
        $this->assertSame($first, $this->admin->fresh()->onboarding_state['finished_at']);

        // Once finished, progress is no longer computed (null) — zero overhead.
        $this->actingAs($this->admin)->get('/school/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('onboarding.finished_at', $first)
                ->where('onboarding.progress', null)
            );
    }

    public function test_reset_replays_the_tour(): void
    {
        $this->actingAs($this->admin)->patch('/school/onboarding', ['tour_seen' => true, 'finished' => true]);
        $this->actingAs($this->admin)->patch('/school/onboarding', ['reset' => true])->assertRedirect();

        $state = $this->admin->fresh()->onboarding_state;
        $this->assertFalse($state['tour_seen']);
        $this->assertNull($state['finished_at']);
    }

    public function test_teacher_progress_is_scoped_to_their_own_work(): void
    {
        $teacher = User::create([
            'name' => 'T', 'email' => 't@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_TEACHER, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $class = SchoolClass::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'grade' => 'Grade 1', 'section' => 'A',
            'academic_year' => '2018 E.C.', 'is_active' => true,
        ]);
        // Another teacher's attendance must NOT count for this teacher.
        AttendanceSession::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'class_id' => $class->id,
            'teacher_id' => $this->admin->id, 'session_date' => today(), 'session_type' => 'morning',
        ]);
        $teacher->assignedClasses()->attach($class->id);

        $this->actingAs($teacher)->get('/school/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('onboarding.progress.has_attendance', false)
                ->where('onboarding.progress.has_grades', false)
            );
    }

    public function test_super_admin_never_receives_onboarding(): void
    {
        $super = User::create([
            'name' => 'Root', 'email' => 'root@arkeon.test', 'password' => Hash::make('password'),
            'role' => User::ROLE_SUPER_ADMIN, 'school_id' => null, 'is_active' => true,
        ]);

        $this->actingAs($super, 'admin')->get('/admin/dashboard')
            ->assertInertia(fn ($page) => $page->where('onboarding', null));
    }
}
