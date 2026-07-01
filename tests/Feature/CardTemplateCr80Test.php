<?php

namespace Tests\Feature;

use App\Models\CardTemplate;
use App\Models\CardTemplateField;
use App\Models\School;
use App\Models\User;
use App\Services\CardTemplateNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CardTemplateCr80Test extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->school = School::create([
            'name' => 'Alpha', 'code' => 'ALPHA', 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 100, 'max_teachers' => 10,
            'hmac_secret' => 'secret', 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);
        $this->superAdmin = User::create([
            'name' => 'Super', 'email' => 'super@test.com', 'password' => Hash::make('password'),
            'role' => 'super_admin', 'school_id' => null, 'is_active' => true,
        ]);
    }

    public function test_cr80_ratio_detection(): void
    {
        $this->assertTrue(CardTemplate::isRatioCr80(1011, 638));   // landscape
        $this->assertTrue(CardTemplate::isRatioCr80(638, 1011));   // portrait
        $this->assertFalse(CardTemplate::isRatioCr80(1808, 2559)); // legacy portrait (0.707)
        $this->assertFalse(CardTemplate::isRatioCr80(1000, 1000)); // square
    }

    public function test_legacy_template_is_flagged(): void
    {
        $legacy = new CardTemplate(['canvas_w' => 1808, 'canvas_h' => 2559]);
        $cr80   = new CardTemplate(['canvas_w' => 638, 'canvas_h' => 1011]);

        $this->assertTrue($legacy->isLegacy());
        $this->assertFalse($cr80->isLegacy());
    }

    public function test_save_rejects_non_cr80_canvas(): void
    {
        $response = $this->actingAs($this->superAdmin)->postJson(
            route('admin.card-template.save', $this->school),
            ['fields' => json_encode([]), 'canvas_w' => 1808, 'canvas_h' => 2559]
        );

        $response->assertStatus(422);
        $this->assertDatabasemissing('card_templates', ['school_id' => $this->school->id]);
    }

    public function test_save_accepts_cr80_canvas_and_sets_orientation(): void
    {
        $this->actingAs($this->superAdmin)->postJson(
            route('admin.card-template.save', $this->school),
            ['fields' => json_encode([]), 'canvas_w' => 638, 'canvas_h' => 1011]
        )->assertOk();

        $t = CardTemplate::withoutGlobalScopes()->where('school_id', $this->school->id)->first();
        $this->assertSame('portrait', $t->orientation);
        $this->assertTrue($t->isCr80Aspect());
    }

    public function test_normalizer_converts_legacy_and_rescales_fields(): void
    {
        // A legacy portrait template (1808×2559) with one field.
        $template = CardTemplate::withoutGlobalScopes()->create([
            'school_id' => $this->school->id, 'template_type' => 'student', 'name' => 'T',
            'canvas_w' => 1808, 'canvas_h' => 2559, 'is_active' => true,
            'front_image_path' => "cards/{$this->school->id}/front.png",
            'created_by' => $this->superAdmin->id,
        ]);
        // Field at native mm corresponding to px (904, 1279) ~ centre.
        $field = CardTemplateField::create([
            'template_id' => $template->id, 'field_type' => 'text', 'field_key' => 'full_name',
            'x_mm' => CardTemplate::pxToMm(904), 'y_mm' => CardTemplate::pxToMm(1279),
            'width_mm' => CardTemplate::pxToMm(400), 'height_mm' => CardTemplate::pxToMm(60),
            'font_size' => 30, 'z_index' => 0, 'side' => 'front',
        ]);

        // Provide a fake source image on the private disk.
        $img = imagecreatetruecolor(1808, 2559);
        ob_start(); imagepng($img); $png = ob_get_clean(); imagedestroy($img);
        Storage::disk('local')->put("cards/{$this->school->id}/front.png", $png);

        $orientation = app(CardTemplateNormalizer::class)->convert($template->fresh());

        $this->assertSame('portrait', $orientation);
        $template->refresh();
        $this->assertSame(638, $template->canvas_w);
        $this->assertSame(1011, $template->canvas_h);
        $this->assertTrue($template->isCr80Aspect());

        // The field kept its relative position (centre stays centre).
        $field->refresh();
        $this->assertEqualsWithDelta(0.5, $field->px_x / 638, 0.02);
        $this->assertEqualsWithDelta(0.5, $field->px_y / 1011, 0.02);
    }

    public function test_save_rejects_canvas_that_mismatches_stored_artwork_orientation(): void
    {
        // A landscape image is on disk, but the save claims a portrait canvas —
        // the orientation toggle was flipped without re-cropping. Must reject.
        $img = imagecreatetruecolor(1011, 638);
        ob_start(); imagepng($img); $png = ob_get_clean(); imagedestroy($img);
        Storage::disk('local')->put("cards/{$this->school->id}/front.png", $png);

        $this->actingAs($this->superAdmin)->postJson(
            route('admin.card-template.save', $this->school),
            ['fields' => json_encode([]), 'canvas_w' => 638, 'canvas_h' => 1011]
        )->assertStatus(422);

        // Matching orientation is accepted.
        $this->actingAs($this->superAdmin)->postJson(
            route('admin.card-template.save', $this->school),
            ['fields' => json_encode([]), 'canvas_w' => 1011, 'canvas_h' => 638]
        )->assertOk();
    }

    public function test_convert_route_migrates_a_legacy_template(): void
    {
        $template = CardTemplate::withoutGlobalScopes()->create([
            'school_id' => $this->school->id, 'template_type' => 'student', 'name' => 'T',
            'canvas_w' => 2000, 'canvas_h' => 1200, 'is_active' => true,
            'front_image_path' => "cards/{$this->school->id}/front.png",
            'created_by' => $this->superAdmin->id,
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('admin.card-template.convert', $this->school))
            ->assertRedirect();

        $template->refresh();
        $this->assertTrue($template->isCr80Aspect());
        $this->assertSame('landscape', $template->orientation);
    }
}
