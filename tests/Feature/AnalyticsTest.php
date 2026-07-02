<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Analytics module: rate math, per-class breakdown, absence leaders, and
 * the role + 'analytics' feature gates (Premium tier).
 */
class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private SchoolClass $class;

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
        $this->class = SchoolClass::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'grade' => 'Grade 4', 'section' => 'A',
            'academic_year' => '2018 E.C.', 'is_active' => true,
        ]);
    }

    private function makeStudent(string $first, string $gender = 'male'): Student
    {
        return Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA-' . strtoupper($first),
            'first_name' => $first, 'last_name' => 'Test', 'gender' => $gender,
            'status' => 'active', 'enrollment_date' => today(), 'class_id' => $this->class->id,
        ]);
    }

    public function test_rate_math_counts_present_and_late_as_attended(): void
    {
        $a = $this->makeStudent('Abebe');
        $b = $this->makeStudent('Sara', 'female');
        $c = $this->makeStudent('Chala');
        $d = $this->makeStudent('Hana', 'female');

        $session = AttendanceSession::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'class_id' => $this->class->id,
            'teacher_id' => $this->admin->id, 'session_date' => today(),
            'session_type' => 'morning',
        ]);
        foreach ([[$a, 'present'], [$b, 'late'], [$c, 'absent'], [$d, 'excused']] as [$s, $st]) {
            AttendanceRecord::create([
                'session_id' => $session->id, 'student_id' => $s->id, 'status' => $st,
            ]);
        }

        $this->actingAs($this->admin)->get('/school/analytics')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('School/Analytics/Index', false)
                // 2 of 4 attended (present + late) = 50%
                ->where('summary.attendance_rate', 50)
                ->where('summary.sessions', 1)
                ->where('summary.active_students', 4)
                ->where('by_class.0.rate', 50)
                ->where('top_absent.0.absences', 1)
                ->where('enrolment.male', 2)
                ->where('enrolment.female', 2)
            );
    }

    public function test_teacher_cannot_view_analytics(): void
    {
        $teacher = User::create([
            'name' => 'T', 'email' => 't@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_TEACHER, 'school_id' => $this->school->id, 'is_active' => true,
        ]);

        $this->actingAs($teacher)->get('/school/analytics')->assertForbidden();
    }

    public function test_non_premium_school_is_gated(): void
    {
        $this->school->update(['plan_tier' => 'standard']);
        $this->school->flushFeatureCache();

        $this->actingAs($this->admin)->get('/school/analytics')->assertForbidden();
    }

    public function test_analytics_seeds_additively_for_existing_installs(): void
    {
        // Simulate an existing install that had a curated matrix BEFORE the
        // analytics feature shipped: remove analytics rows + registry entry,
        // keep everything else.
        \DB::table('plan_features')->where('feature_key', 'analytics')->delete();
        \DB::table('feature_registry')->where('key', 'analytics')->delete();

        // The admin's custom state must survive, analytics must appear.
        \DB::table('plan_features')->where('plan_tier', 'starter')
            ->where('feature_key', 'gradebook')->delete();

        $this->seed(\Database\Seeders\FeatureSeeder::class);

        $this->assertDatabaseHas('plan_features', ['plan_tier' => 'premium', 'feature_key' => 'analytics']);
        $this->assertDatabaseMissing('plan_features', ['plan_tier' => 'starter', 'feature_key' => 'gradebook']);
    }
}
