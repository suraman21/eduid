<?php

namespace Tests\Feature;

use App\Models\CardOrder;
use App\Models\CardTemplate;
use App\Models\CardTemplateField;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\CardImageService;
use App\Services\CardPrintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Exercises the real GD (PNG) and TCPDF (PDF) render pipelines end-to-end from
 * a template with a background image + positioned fields. This is the coverage
 * that was missing when a corrupt font shipped and 500'd PNG generation.
 */
class CardRenderTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Render School', 'code' => 'RND', 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 100, 'max_teachers' => 10,
            'hmac_secret' => 'render-secret', 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'a@rnd.com', 'password' => Hash::make('x'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $this->school->id, 'is_active' => true,
        ]);
        $class = SchoolClass::create([
            'school_id' => $this->school->id, 'grade' => 'Grade 10', 'section' => 'A',
            'level_order' => 10, 'is_active' => true, 'academic_year' => '2018 E.C.',
        ]);
        $this->student = Student::create([
            'school_id' => $this->school->id, 'class_id' => $class->id, 'uid' => 'RND-00001',
            'first_name' => 'Abebe', 'last_name' => 'Kebede', 'gender' => 'male', 'status' => 'active',
            'enrollment_date' => '2024-09-01', 'date_of_birth' => '2010-04-14', 'blood_type' => 'O+',
        ]);
    }

    private function makeTemplate(): CardTemplate
    {
        // A real white PNG background on the private disk.
        $img = imagecreatetruecolor(1012, 638);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);
        Storage::disk('local')->put("cards/{$this->school->id}/front.png", $png);

        $template = CardTemplate::create([
            'school_id' => $this->school->id, 'name' => 'T', 'template_type' => 'student',
            'front_image_path' => "cards/{$this->school->id}/front.png",
            'canvas_w' => 1012, 'canvas_h' => 638, 'is_active' => true, 'created_by' => $this->admin->id,
        ]);

        // Text fields with realistic canvas-pixel font sizes (40, 24, 22…).
        foreach ([['full_name', 40], ['uid', 24], ['date_of_birth', 22], ['blood_type', 22]] as [$key, $size]) {
            CardTemplateField::create([
                'template_id' => $template->id, 'field_type' => 'text', 'field_key' => $key,
                'x_mm' => 6, 'y_mm' => 10, 'width_mm' => 50, 'height_mm' => 8,
                'font_size' => $size, 'side' => 'front',
            ]);
        }
        CardTemplateField::create([
            'template_id' => $template->id, 'field_type' => 'qr', 'field_key' => 'qr_code',
            'x_mm' => 65, 'y_mm' => 8, 'width_mm' => 15, 'height_mm' => 15, 'side' => 'front',
        ]);

        return $template->load('fields');
    }

    private function makeOrder(): CardOrder
    {
        return CardOrder::create([
            'school_id' => $this->school->id, 'order_code' => 'ORD-RND-2026-0001',
            'ordered_by' => $this->admin->id, 'student_ids' => [$this->student->id],
            'student_count' => 1, 'order_type' => 'first_batch', 'status' => 'confirmed',
            'amount_etb' => 0,
        ]);
    }

    public function test_png_zip_generates_without_error(): void
    {
        Storage::fake('local');
        $template = $this->makeTemplate();
        $order    = $this->makeOrder();

        $path = app(CardImageService::class)->generateZip($order, $template);

        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertGreaterThan(0, Storage::disk('local')->size($path));
    }

    public function test_pdf_generates_from_template_without_error(): void
    {
        Storage::fake('local');
        $this->makeTemplate();
        $order = $this->makeOrder();

        $path = app(CardPrintService::class)->generate($order);

        $this->assertTrue(Storage::disk('local')->exists($path));
        // A real one-card PDF is comfortably over 1 KB.
        $this->assertGreaterThan(1000, Storage::disk('local')->size($path));
    }
}
