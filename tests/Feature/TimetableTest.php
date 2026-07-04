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
use Tests\TestCase;

/**
 * Timetable builder: bell-schedule CRUD, grid entry upsert/clear, teacher
 * double-booking prevention, and the role + feature gates.
 */
class TimetableTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private User $teacher;
    private SchoolClass $classA;
    private SchoolClass $classB;
    private Subject $english;
    private TimetablePeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        // Standard tier — includes the 'timetable' module per the seeded matrix.
        $this->school = School::create([
            'name' => 'Alpha', 'code' => 'ALPHA', 'is_active' => true,
            'plan_tier' => 'standard', 'max_students' => 800, 'max_teachers' => 30,
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
        $this->period = TimetablePeriod::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'label' => 'Period 1',
            'start_time' => '08:00', 'end_time' => '08:40', 'sort_order' => 1,
        ]);
    }

    public function test_admin_can_view_builder_and_add_period(): void
    {
        $this->actingAs($this->admin)->get('/school/timetable')->assertOk();

        $this->actingAs($this->admin)->post('/school/timetable/periods', [
            'label' => 'Period 2', 'start_time' => '08:45', 'end_time' => '09:25',
        ])->assertRedirect();

        $this->assertDatabaseHas('timetable_periods', [
            'school_id' => $this->school->id, 'label' => 'Period 2', 'sort_order' => 2,
        ]);
    }

    public function test_teacher_cannot_access_builder(): void
    {
        $this->actingAs($this->teacher)->get('/school/timetable')->assertForbidden();
    }

    public function test_starter_school_lacking_module_is_gated(): void
    {
        $starter = School::create([
            'name' => 'Beta', 'code' => 'BETA', 'is_active' => true,
            'plan_tier' => 'starter', 'max_students' => 300, 'max_teachers' => 10,
            'hmac_secret' => 'secret2', 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);
        $starterAdmin = User::create([
            'name' => 'B Admin', 'email' => 'admin@beta.test', 'password' => Hash::make('password'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $starter->id, 'is_active' => true,
        ]);

        $this->actingAs($starterAdmin)->get('/school/timetable')->assertForbidden();
    }

    public function test_entry_upserts_instead_of_duplicating(): void
    {
        $payload = [
            'class_id'    => $this->classA->id,
            'period_id'   => $this->period->id,
            'day_of_week' => 1,
            'subject_id'  => $this->english->id,
            'teacher_id'  => $this->teacher->id,
            'room'        => 'B-1',
        ];

        $this->actingAs($this->admin)->post('/school/timetable/entries', $payload)->assertRedirect();
        $this->actingAs($this->admin)->post('/school/timetable/entries', array_merge($payload, ['room' => 'B-9']))->assertRedirect();

        $rows = TimetableEntry::withoutGlobalScope('school')
            ->where('class_id', $this->classA->id)
            ->where('period_id', $this->period->id)
            ->where('day_of_week', 1)->get();

        $this->assertCount(1, $rows);
        $this->assertSame('B-9', $rows->first()->room);
    }

    public function test_teacher_double_booking_is_blocked(): void
    {
        // Teacher already teaches class A, Monday period 1.
        TimetableEntry::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'class_id' => $this->classA->id,
            'period_id' => $this->period->id, 'day_of_week' => 1,
            'subject_id' => $this->english->id, 'teacher_id' => $this->teacher->id,
        ]);

        // Booking the same teacher for class B in the same slot must fail.
        $this->actingAs($this->admin)->post('/school/timetable/entries', [
            'class_id'    => $this->classB->id,
            'period_id'   => $this->period->id,
            'day_of_week' => 1,
            'subject_id'  => $this->english->id,
            'teacher_id'  => $this->teacher->id,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('timetable_entries', [
            'class_id' => $this->classB->id, 'period_id' => $this->period->id, 'day_of_week' => 1,
        ]);
    }

    public function test_clear_entry_deletes_the_lesson(): void
    {
        TimetableEntry::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'class_id' => $this->classA->id,
            'period_id' => $this->period->id, 'day_of_week' => 2,
            'subject_id' => $this->english->id, 'teacher_id' => $this->teacher->id,
        ]);

        $this->actingAs($this->admin)->delete('/school/timetable/entries', [
            'class_id' => $this->classA->id, 'period_id' => $this->period->id, 'day_of_week' => 2,
        ])->assertRedirect();

        $this->assertDatabaseMissing('timetable_entries', [
            'class_id' => $this->classA->id, 'period_id' => $this->period->id, 'day_of_week' => 2,
        ]);
    }

    public function test_deleting_a_period_cascades_its_lessons(): void
    {
        TimetableEntry::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'class_id' => $this->classA->id,
            'period_id' => $this->period->id, 'day_of_week' => 3,
            'subject_id' => $this->english->id, 'teacher_id' => $this->teacher->id,
        ]);

        $this->actingAs($this->admin)->delete("/school/timetable/periods/{$this->period->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('timetable_periods', ['id' => $this->period->id]);
        $this->assertDatabaseMissing('timetable_entries', ['period_id' => $this->period->id]);
    }

    // ── Working days ─────────────────────────────────────────────

    public function test_unset_working_days_defaults_to_mon_sat(): void
    {
        $this->assertSame([1, 2, 3, 4, 5, 6], $this->school->fresh()->workingDays());

        $this->actingAs($this->admin)->get('/school/timetable')
            ->assertInertia(fn ($page) => $page->where('working_days', [1, 2, 3, 4, 5, 6]));
    }

    public function test_admin_can_set_a_mon_fri_week(): void
    {
        $this->actingAs($this->admin)
            ->patch('/school/timetable/working-days', ['working_days' => [1, 2, 3, 4, 5]])
            ->assertRedirect();

        $this->assertSame([1, 2, 3, 4, 5], $this->school->fresh()->workingDays());
    }

    public function test_removing_a_day_clears_its_lessons(): void
    {
        // A Saturday (6) lesson exists.
        TimetableEntry::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'class_id' => $this->classA->id,
            'period_id' => $this->period->id, 'day_of_week' => 6,
            'subject_id' => $this->english->id, 'teacher_id' => $this->teacher->id,
        ]);

        // Switch to a Mon–Fri week — the Saturday lesson must be swept away.
        $this->actingAs($this->admin)
            ->patch('/school/timetable/working-days', ['working_days' => [1, 2, 3, 4, 5]])
            ->assertRedirect();

        $this->assertDatabaseMissing('timetable_entries', [
            'class_id' => $this->classA->id, 'day_of_week' => 6,
        ]);
    }

    public function test_cannot_schedule_a_lesson_on_a_non_working_day(): void
    {
        $this->school->update(['working_days' => [1, 2, 3, 4, 5]]);

        // Sunday (7) is not a working day → rejected, nothing written.
        $this->actingAs($this->admin)->post('/school/timetable/entries', [
            'class_id'    => $this->classA->id,
            'period_id'   => $this->period->id,
            'day_of_week' => 7,
            'subject_id'  => $this->english->id,
            'teacher_id'  => $this->teacher->id,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('timetable_entries', [
            'class_id' => $this->classA->id, 'day_of_week' => 7,
        ]);
    }

    public function test_teacher_cannot_change_working_days(): void
    {
        $this->actingAs($this->teacher)
            ->patch('/school/timetable/working-days', ['working_days' => [1, 2, 3]])
            ->assertForbidden();
    }

    public function test_working_days_cannot_be_empty(): void
    {
        $this->actingAs($this->admin)
            ->patch('/school/timetable/working-days', ['working_days' => []])
            ->assertSessionHasErrors('working_days');
    }

    // ── Makeup / extra classes ───────────────────────────────────

    public function test_admin_can_schedule_and_remove_a_makeup_class(): void
    {
        $date = now()->addDays(3)->toDateString();

        $this->actingAs($this->admin)->post('/school/timetable/makeup', [
            'class_id' => $this->classA->id, 'date' => $date,
            'start_time' => '14:00', 'end_time' => '15:00',
            'subject_id' => $this->english->id, 'teacher_id' => $this->teacher->id,
            'reason' => 'Makeup for holiday',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('makeup_classes', [
            'school_id' => $this->school->id, 'class_id' => $this->classA->id,
            'teacher_id' => $this->teacher->id,
        ]);
        $created = \App\Models\MakeupClass::withoutGlobalScope('school')->first();
        $this->assertSame($date, $created->date->toDateString());

        $id = $created->id;
        $this->actingAs($this->admin)->delete("/school/timetable/makeup/{$id}")->assertRedirect();
        $this->assertDatabaseMissing('makeup_classes', ['id' => $id]);
    }

    public function test_makeup_cannot_be_scheduled_in_the_past(): void
    {
        $this->actingAs($this->admin)->post('/school/timetable/makeup', [
            'class_id' => $this->classA->id, 'date' => now()->subDay()->toDateString(),
            'start_time' => '14:00', 'end_time' => '15:00',
        ])->assertSessionHasErrors('date');
    }

    public function test_makeup_blocks_teacher_already_in_a_regular_lesson(): void
    {
        // Teacher teaches period 1 (08:00–08:40) every Monday.
        TimetableEntry::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'class_id' => $this->classA->id,
            'period_id' => $this->period->id, 'day_of_week' => 1,
            'subject_id' => $this->english->id, 'teacher_id' => $this->teacher->id,
        ]);

        // Next Monday, 08:15–09:00 overlaps that recurring lesson → rejected.
        $nextMonday = now()->next(\Carbon\Carbon::MONDAY)->toDateString();
        $this->actingAs($this->admin)->post('/school/timetable/makeup', [
            'class_id' => $this->classB->id, 'date' => $nextMonday,
            'start_time' => '08:15', 'end_time' => '09:00',
            'teacher_id' => $this->teacher->id,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseCount('makeup_classes', 0);
    }

    public function test_teacher_cannot_create_makeup(): void
    {
        $this->actingAs($this->teacher)->post('/school/timetable/makeup', [
            'class_id' => $this->classA->id, 'date' => now()->addDay()->toDateString(),
            'start_time' => '14:00', 'end_time' => '15:00',
        ])->assertForbidden();
    }

    public function test_mobile_today_endpoint_returns_makeup_classes(): void
    {
        \App\Models\MakeupClass::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'class_id' => $this->classA->id,
            'subject_id' => $this->english->id, 'teacher_id' => $this->teacher->id,
            'date' => today()->toDateString(), 'start_time' => '14:00', 'end_time' => '15:00',
            'reason' => 'Revision',
        ]);

        $this->actingAs($this->teacher, 'sanctum')->getJson('/api/v1/schedule/today')
            ->assertOk()
            ->assertJsonPath('makeup_classes.0.subject_name', 'English')
            ->assertJsonPath('makeup_classes.0.reason', 'Revision');
    }
}
