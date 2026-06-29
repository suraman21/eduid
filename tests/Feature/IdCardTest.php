<?php

namespace Tests\Feature;

use App\Models\CardOrder;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IdCardTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private User $teacher;
    private SchoolClass $class;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Test School', 'code' => 'TST', 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 500, 'max_teachers' => 50,
            'hmac_secret' => 'test-secret', 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@tst.com', 'password' => Hash::make('x'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $this->school->id, 'is_active' => true,
        ]);

        $this->teacher = User::create([
            'name' => 'Teacher', 'email' => 'teacher@tst.com', 'password' => Hash::make('x'),
            'role' => User::ROLE_TEACHER, 'school_id' => $this->school->id, 'is_active' => true,
        ]);

        $this->class = SchoolClass::create([
            'school_id' => $this->school->id, 'grade' => '5', 'section' => 'A',
            'level_order' => 5, 'is_active' => true, 'academic_year' => '2018 E.C.',
        ]);

        $this->student = Student::create([
            'school_id'       => $this->school->id,
            'class_id'        => $this->class->id,
            'uid'             => 'STU-001',
            'first_name'      => 'Abebe',
            'last_name'       => 'Kebede',
            'gender'          => 'male',
            'status'          => 'active',
            'enrollment_date' => '2024-09-01',
            'qr_token'        => 'tok123',
            'qr_token_year'   => '2018 E.C.',
        ]);
    }

    public function test_guest_cannot_view_id_card(): void
    {
        $response = $this->get(route('school.students.id-card', $this->student));
        $response->assertRedirect();
    }

    public function test_admin_can_view_student_id_card(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('school.students.id-card', $this->student));

        $response->assertOk();
        $response->assertSee($this->student->full_name);
        $response->assertSee($this->student->uid);
    }

    public function test_id_card_contains_qr_svg(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('school.students.id-card', $this->student));

        $response->assertOk();
        // QR SVG is rendered inline
        $response->assertSee('<svg', false);
        $response->assertSee('viewBox', false);
    }

    public function test_teacher_assigned_to_class_can_view_id_card(): void
    {
        \Illuminate\Support\Facades\DB::table('teacher_classes')->insert([
            'user_id' => $this->teacher->id, 'class_id' => $this->class->id,
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('school.students.id-card', $this->student));

        $response->assertOk();
    }

    public function test_teacher_not_assigned_to_class_cannot_view_id_card(): void
    {
        $response = $this->actingAs($this->teacher)
            ->get(route('school.students.id-card', $this->student));

        $response->assertForbidden();
    }

    public function test_cross_tenant_student_id_card_returns_404(): void
    {
        $otherSchool = School::create([
            'name' => 'Other', 'code' => 'OTH', 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 100, 'max_teachers' => 10,
            'hmac_secret' => 'other-secret', 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);
        $otherClass = SchoolClass::create([
            'school_id' => $otherSchool->id, 'grade' => '3', 'section' => 'B',
            'level_order' => 3, 'is_active' => true, 'academic_year' => '2018 E.C.',
        ]);
        $otherStudent = Student::create([
            'school_id' => $otherSchool->id, 'class_id' => $otherClass->id,
            'uid' => 'OTH-001', 'first_name' => 'Hirut', 'last_name' => 'Alemu',
            'gender' => 'female', 'status' => 'active', 'enrollment_date' => '2024-09-01',
        ]);

        // Admin from school 1 can't access student from school 2 (global scope)
        $response = $this->actingAs($this->admin)
            ->get(route('school.students.id-card', $otherStudent));

        $response->assertNotFound();
    }

    public function test_admin_can_preview_batch_id_cards(): void
    {
        $order = CardOrder::create([
            'school_id'     => $this->school->id,
            'order_code'    => 'ORD-TST-2018-001',
            'ordered_by'    => $this->admin->id,
            'student_ids'   => [$this->student->id],
            'student_count' => 1,
            'order_type'    => 'first_batch',
            'status'        => 'pending',
            'amount_etb'    => 0,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('school.card-orders.id-cards', $order));

        $response->assertOk();
        $response->assertSee($this->student->full_name);
        $response->assertSee($order->order_code);
    }

    public function test_teacher_cannot_access_batch_preview(): void
    {
        $order = CardOrder::create([
            'school_id'     => $this->school->id,
            'order_code'    => 'ORD-TST-2018-002',
            'ordered_by'    => $this->admin->id,
            'student_ids'   => [$this->student->id],
            'student_count' => 1,
            'order_type'    => 'first_batch',
            'status'        => 'pending',
            'amount_etb'    => 0,
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('school.card-orders.id-cards', $order));

        $response->assertForbidden();
    }

    public function test_id_card_uses_template_when_one_exists(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put(
            "cards/{$this->school->id}/front.png", 'fake-png-bytes'
        );

        $template = \App\Models\CardTemplate::create([
            'school_id'        => $this->school->id,
            'name'             => 'Student Card',
            'template_type'    => 'student',
            'front_image_path' => "cards/{$this->school->id}/front.png",
            'canvas_w'         => 1012,
            'canvas_h'         => 638,
            'is_active'        => true,
            'created_by'       => $this->admin->id,
        ]);
        \App\Models\CardTemplateField::create([
            'template_id' => $template->id, 'field_type' => 'text', 'field_key' => 'full_name',
            'x_mm' => 10, 'y_mm' => 10, 'width_mm' => 40, 'height_mm' => 8, 'side' => 'front',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('school.students.id-card', $this->student));

        $response->assertOk();
        // The template payload must be present so the school sees the designed card,
        // not the branded fallback — even though no public-disk copy exists.
        $response->assertInertia(fn ($page) => $page
            ->where('template.canvasW', 1012)
            ->has('template.fields', 1)
        );
    }

    public function test_template_image_url_is_cache_busted(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put(
            "cards/{$this->school->id}/front.png", 'fake-png-bytes'
        );
        \App\Models\CardTemplate::create([
            'school_id' => $this->school->id, 'name' => 'Student Card', 'template_type' => 'student',
            'front_image_path' => "cards/{$this->school->id}/front.png",
            'canvas_w' => 1011, 'canvas_h' => 638, 'is_active' => true, 'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('school.students.id-card', $this->student));

        $response->assertOk();
        // The background URL must carry a ?v= cache-buster so a re-uploaded design
        // refreshes immediately instead of serving the browser-cached old image.
        $response->assertInertia(fn ($page) => $page
            ->where('template.frontUrl', fn ($url) => is_string($url) && str_contains($url, '?v='))
        );
    }

    public function test_template_image_is_tenant_scoped(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put(
            "cards/{$this->school->id}/front.png", 'fake-png-bytes'
        );

        $this->actingAs($this->admin)
            ->get(route('school.id-card.template-image', 'front'))
            ->assertOk();

        // A non-existent side 404s rather than leaking anything.
        $this->actingAs($this->admin)
            ->get(route('school.id-card.template-image', 'back'))
            ->assertNotFound();
    }
}
