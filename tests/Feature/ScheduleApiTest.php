<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $teacher;

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
    }

    public function test_today_schedule_returns_periods(): void
    {
        $period = TimetablePeriod::create([
            'school_id' => $this->school->id, 'label' => 'Period 1',
            'start_time' => '08:00', 'end_time' => '08:45', 'sort_order' => 1,
        ]);

        $class = SchoolClass::create([
            'school_id' => $this->school->id, 'grade' => '5', 'section' => 'A',
            'is_active' => true, 'academic_year' => '2018 E.C.',
        ]);

        $subject = Subject::create([
            'school_id' => $this->school->id, 'name' => 'Math', 'code' => 'MAT',
        ]);

        $dayOfWeek = (int) now()->dayOfWeekIso;

        TimetableEntry::create([
            'school_id' => $this->school->id, 'class_id' => $class->id,
            'period_id' => $period->id, 'day_of_week' => $dayOfWeek,
            'subject_id' => $subject->id, 'teacher_id' => $this->teacher->id,
            'room' => 'A101',
        ]);

        Sanctum::actingAs($this->teacher);

        $response = $this->getJson('/api/v1/schedule/today');
        $response->assertOk();
        $response->assertJsonPath('schedule.0.label', 'Period 1');
        $response->assertJsonPath('schedule.0.entry.subject_name', 'Math');
        $response->assertJsonPath('schedule.0.entry.room', 'A101');
    }

    public function test_week_schedule_returns_working_days_only(): void
    {
        Sanctum::actingAs($this->teacher);

        $response = $this->getJson('/api/v1/schedule/week');
        $response->assertOk();
        // Default week is Mon–Sat (6 working days); Sunday must NOT appear.
        $response->assertJsonCount(6, 'week');
        $this->assertNotContains(7, collect($response->json('week'))->pluck('day')->all());
    }
}
