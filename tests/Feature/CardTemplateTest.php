<?php

namespace Tests\Feature;

use App\Models\CardTemplate;
use App\Models\CardTemplateField;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CardTemplateTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $superAdmin;
    private User $schoolAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->school = School::create([
            'name'                => 'Template School',
            'code'                => 'TMPL',
            'is_active'           => true,
            'plan_tier'           => 'premium',
            'max_students'        => 500,
            'max_teachers'        => 50,
            'hmac_secret'         => 'secret',
            'academic_year'       => '2018 E.C.',
            'subscription_status' => 'active',
        ]);

        $this->superAdmin = User::create([
            'name'      => 'Super',
            'email'     => 'super@test.com',
            'password'  => Hash::make('password'),
            'role'      => 'super_admin',
            'school_id' => null,
            'is_active' => true,
        ]);

        $this->schoolAdmin = User::create([
            'name'      => 'SchoolAdmin',
            'email'     => 'sadmin@test.com',
            'password'  => Hash::make('password'),
            'role'      => User::ROLE_SCHOOL_ADMIN,
            'school_id' => $this->school->id,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_view_template_editor(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.card-template.edit', $this->school));

        $response->assertOk();
        $response->assertSee('Card Template Editor');
    }

    public function test_school_admin_cannot_access_template_editor(): void
    {
        $response = $this->actingAs($this->schoolAdmin)
            ->get(route('admin.card-template.edit', $this->school));

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_from_template_editor(): void
    {
        $response = $this->get(route('admin.card-template.edit', $this->school));
        $response->assertRedirect();
    }

    public function test_upload_image_stores_png_and_returns_json(): void
    {
        $file = UploadedFile::fake()->image('front.png', 1012, 638);

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('admin.card-template.upload', $this->school), [
                'side'  => 'front',
                'image' => $file,
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['success', 'url']);
        $response->assertJson(['success' => true]);
        Storage::disk('local')->assertExists("cards/{$this->school->id}/front.png");
    }

    public function test_upload_rejects_invalid_side(): void
    {
        $file = UploadedFile::fake()->image('bad.png', 100, 100);

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('admin.card-template.upload', $this->school), [
                'side'  => 'side3',
                'image' => $file,
            ]);

        $response->assertUnprocessable();
    }

    public function test_save_fields_creates_template_and_fields(): void
    {
        Storage::disk('local')->put("cards/{$this->school->id}/front.png", 'fake');

        $fields = json_encode([
            ['field_key' => 'full_name', 'x' => 100, 'y' => 50, 'w' => 300, 'h' => 30,
             'font_family' => 'Inter', 'font_size' => 14, 'font_color' => '#ffffff',
             'text_align' => 'left', 'font_weight' => 'normal', 'custom_value' => null],
            ['field_key' => 'photo', 'x' => 50, 'y' => 100, 'w' => 80, 'h' => 100,
             'font_family' => 'Inter', 'font_size' => 12, 'font_color' => '#000000',
             'text_align' => 'left', 'font_weight' => 'normal', 'custom_value' => null],
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.card-template.save', $this->school), [
                'fields' => $fields,
                'name'   => 'Test Template',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('card_templates', [
            'school_id'     => $this->school->id,
            'template_type' => 'student',
            'name'          => 'Test Template',
        ]);
        $this->assertDatabaseCount('card_template_fields', 2);
    }

    public function test_save_fields_replaces_existing_fields(): void
    {
        Storage::disk('local')->put("cards/{$this->school->id}/front.png", 'fake');

        $save = fn(array $fieldList) => $this->actingAs($this->superAdmin)
            ->post(route('admin.card-template.save', $this->school), [
                'fields' => json_encode($fieldList),
            ]);

        $base = ['font_family' => 'Inter', 'font_size' => 12, 'font_color' => '#000000',
                 'text_align' => 'left', 'font_weight' => 'normal', 'custom_value' => null];

        $save([array_merge($base, ['field_key' => 'uid', 'x' => 10, 'y' => 10, 'w' => 100, 'h' => 20])]);

        $save([
            array_merge($base, ['field_key' => 'full_name',  'x' => 20, 'y' => 20, 'w' => 200, 'h' => 25]),
            array_merge($base, ['field_key' => 'grade_class','x' => 20, 'y' => 60, 'w' => 150, 'h' => 20]),
        ]);

        $this->assertDatabaseCount('card_template_fields', 2);
        $this->assertDatabaseMissing('card_template_fields', ['field_key' => 'uid']);
    }

    public function test_serve_image_returns_404_when_no_image(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.card-template.image', [$this->school, 'front']));

        $response->assertNotFound();
    }

    public function test_serve_image_streams_png(): void
    {
        Storage::disk('local')->put("cards/{$this->school->id}/front.png", 'fake-png-data');

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.card-template.image', [$this->school, 'front']));

        $response->assertOk();
    }

    public function test_template_editor_shows_existing_fields(): void
    {
        $template = CardTemplate::create([
            'school_id'        => $this->school->id,
            'name'             => 'Existing Template',
            'template_type'    => 'student',
            'front_image_path' => "cards/{$this->school->id}/front.png",
            'is_active'        => true,
            'version'          => 1,
            'created_by'       => $this->superAdmin->id,
        ]);
        CardTemplateField::create([
            'template_id'  => $template->id,
            'field_type'   => 'text',
            'field_key'    => 'full_name',
            'label'        => 'Full Name',
            'x_mm'         => 8.47,
            'y_mm'         => 4.23,
            'font_size'    => 14,
            'font_color'   => '#ffffff',
            'font_family'  => 'Inter',
            'text_align'   => 'left',
            'font_weight'  => 'normal',
            'z_index'      => 0,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.card-template.edit', $this->school));

        $response->assertOk();
        $response->assertSee('full_name');
    }
}
