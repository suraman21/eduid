<?php

namespace Tests\Feature;

use App\Models\ClassSubject;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\YearTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Academic Year Promotion (PRD §06): promote/repeat/graduate per class,
 * school year turns over, EVERY active student's QR token is re-minted so
 * old cards stop verifying, old classes archive, subjects carry forward.
 */
class YearTransitionTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private SchoolClass $grade1;
    private SchoolClass $grade12;
    private Student $younger;
    private Student $senior;

    protected function setUp(): void
    {
        parent::setUp();

        // Standard tier — includes 'year_transition' per the seeded matrix.
        $this->school = School::create([
            'name' => 'Alpha', 'code' => 'ALPHA', 'is_active' => true,
            'plan_tier' => 'standard', 'max_students' => 800, 'max_teachers' => 30,
            'hmac_secret' => 'super-secret', 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->grade1 = SchoolClass::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'grade' => 'Grade 1', 'section' => 'A',
            'academic_year' => '2018 E.C.', 'level_order' => 2, 'is_active' => true,
        ]);
        $this->grade12 = SchoolClass::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'grade' => 'Grade 12', 'section' => 'A',
            'academic_year' => '2018 E.C.', 'level_order' => 13, 'is_active' => true,
        ]);
        $this->younger = Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA-2018-000001',
            'first_name' => 'Sara', 'last_name' => 'Test', 'gender' => 'female',
            'status' => 'active', 'enrollment_date' => today(),
            'class_id' => $this->grade1->id,
            'qr_token' => 'old-token-1', 'qr_token_year' => '2018 E.C.',
        ]);
        $this->senior = Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA-2018-000002',
            'first_name' => 'Abel', 'last_name' => 'Test', 'gender' => 'male',
            'status' => 'active', 'enrollment_date' => today(),
            'class_id' => $this->grade12->id,
            'qr_token' => 'old-token-2', 'qr_token_year' => '2018 E.C.',
        ]);
    }

    private function runTransition(): void
    {
        $this->actingAs($this->admin)->post('/school/year-transition', [
            'new_year'  => '2019 E.C.',
            'confirmed' => true,
            'mappings'  => [
                ['class_id' => $this->grade1->id,  'action' => 'promote', 'target_grade' => 'Grade 2'],
                ['class_id' => $this->grade12->id, 'action' => 'graduate', 'target_grade' => null],
            ],
        ])->assertRedirect('/school/dashboard')->assertSessionHas('success');
    }

    public function test_full_transition_promotes_graduates_and_renews_tokens(): void
    {
        $this->runTransition();

        // School year turned over.
        $this->assertSame('2019 E.C.', $this->school->fresh()->academic_year);

        // Promoted student sits in a NEW Grade 2 - A class for the new year.
        $newClass = SchoolClass::withoutGlobalScope('school')
            ->where('school_id', $this->school->id)
            ->where('grade', 'Grade 2')->where('section', 'A')
            ->where('academic_year', '2019 E.C.')->firstOrFail();
        $this->assertSame($newClass->id, $this->younger->fresh()->class_id);
        $this->assertSame(3, $newClass->level_order);

        // Senior graduated — inactive roster, seat freed.
        $this->assertSame('graduated', $this->senior->fresh()->status);

        // Old classes archived.
        $this->assertFalse((bool) $this->grade1->fresh()->is_active);
        $this->assertFalse((bool) $this->grade12->fresh()->is_active);

        // Active student's token re-minted for the NEW year (old card dead).
        $fresh = $this->younger->fresh();
        $this->assertSame('2019 E.C.', $fresh->qr_token_year);
        $this->assertNotSame('old-token-1', $fresh->qr_token);
        $this->assertSame(
            hash_hmac('sha256', "{$fresh->uid}|2019 E.C.", 'super-secret'),
            $fresh->qr_token
        );

        // Graduated student keeps the stale token — it can never verify again.
        $this->assertSame('2018 E.C.', $this->senior->fresh()->qr_token_year);
    }

    public function test_subjects_carry_forward_to_the_new_class(): void
    {
        $english = Subject::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'name' => 'English', 'code' => 'ENG', 'is_active' => true,
        ]);
        ClassSubject::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'class_id' => $this->grade1->id,
            'subject_id' => $english->id, 'teacher_id' => $this->admin->id, 'periods_per_week' => 6,
        ]);

        $this->runTransition();

        $newClass = SchoolClass::withoutGlobalScope('school')
            ->where('grade', 'Grade 2')->where('academic_year', '2019 E.C.')->firstOrFail();

        $carried = ClassSubject::withoutGlobalScope('school')
            ->where('class_id', $newClass->id)->where('subject_id', $english->id)->first();

        $this->assertNotNull($carried);
        $this->assertSame(6, $carried->periods_per_week);
        // Teachers are NOT carried — assignments are per-year decisions.
        $this->assertNull($carried->teacher_id);
    }

    public function test_every_active_class_must_be_mapped(): void
    {
        $this->actingAs($this->admin)->post('/school/year-transition', [
            'new_year'  => '2019 E.C.',
            'confirmed' => true,
            'mappings'  => [
                ['class_id' => $this->grade1->id, 'action' => 'promote', 'target_grade' => 'Grade 2'],
                // grade12 missing!
            ],
        ])->assertSessionHasErrors('mappings');

        // Nothing changed.
        $this->assertSame('2018 E.C.', $this->school->fresh()->academic_year);
        $this->assertSame('active', $this->senior->fresh()->status);
    }

    public function test_same_year_is_rejected(): void
    {
        $this->actingAs($this->admin)->post('/school/year-transition', [
            'new_year'  => '2018 E.C.',
            'confirmed' => true,
            'mappings'  => [
                ['class_id' => $this->grade1->id,  'action' => 'promote', 'target_grade' => 'Grade 2'],
                ['class_id' => $this->grade12->id, 'action' => 'graduate'],
            ],
        ])->assertSessionHasErrors('new_year');
    }

    public function test_requires_explicit_confirmation(): void
    {
        $this->actingAs($this->admin)->post('/school/year-transition', [
            'new_year' => '2019 E.C.',
            'mappings' => [
                ['class_id' => $this->grade1->id,  'action' => 'promote', 'target_grade' => 'Grade 2'],
                ['class_id' => $this->grade12->id, 'action' => 'graduate'],
            ],
        ])->assertSessionHasErrors('confirmed');

        $this->assertSame('2018 E.C.', $this->school->fresh()->academic_year);
    }

    public function test_teacher_and_starter_tier_cannot_run_it(): void
    {
        $teacher = User::create([
            'name' => 'Teacher', 'email' => 'teacher@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_TEACHER, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $this->actingAs($teacher)->get('/school/year-transition')->assertForbidden();

        // Starter tier lacks the year_transition module.
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
        $this->actingAs($starterAdmin)->get('/school/year-transition')->assertForbidden();
    }

    public function test_year_and_grade_suggestions(): void
    {
        $this->assertSame('2019 E.C.', YearTransitionService::suggestNextYear('2018 E.C.'));
        $this->assertSame('2027/2028', YearTransitionService::suggestNextYear('2026/2027'));
        $this->assertSame('Grade 10', YearTransitionService::suggestNextGrade('Grade 9'));
        $this->assertSame('KG-2', YearTransitionService::suggestNextGrade('KG-1'));
        $this->assertSame('Nursery', YearTransitionService::suggestNextGrade('Nursery'));
    }
}
