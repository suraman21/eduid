<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SheetTemplate;
use App\Models\Student;
use App\Services\SheetPrintService;
use Database\Seeders\SheetTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SheetPrintTest extends TestCase
{
    use RefreshDatabase;

    private function seedSheet(): SheetTemplate
    {
        (new SheetTemplateSeeder)->run();

        return SheetTemplate::where('key', 'a4-10up')->firstOrFail();
    }

    public function test_a4_10up_template_is_seeded(): void
    {
        $sheet = $this->seedSheet();

        $this->assertSame('A4 PVC (10 Cards)', $sheet->name);
        $this->assertSame(2, $sheet->columns);
        $this->assertSame(5, $sheet->rows);
        $this->assertSame(10, $sheet->slotCount());
    }

    public function test_seeder_is_idempotent(): void
    {
        (new SheetTemplateSeeder)->run();
        (new SheetTemplateSeeder)->run();

        $this->assertSame(1, SheetTemplate::where('key', 'a4-10up')->count());
    }

    public function test_slot_positions_fill_grid_left_to_right_top_to_bottom(): void
    {
        $sheet = $this->seedSheet();
        $pos = $sheet->slotPositions();

        // 10 slots, 1-based.
        $this->assertCount(10, $pos);

        // Slot 1: top-left corner = (margin_left, margin_top).
        $this->assertEqualsWithDelta(13.0, $pos[1]['x'], 0.001);
        $this->assertEqualsWithDelta(8.0, $pos[1]['y'], 0.001);

        // Slot 2: second column, same row → x advances by card_w + h_spacing.
        $this->assertEqualsWithDelta(13.0 + 85.6 + 12.8, $pos[2]['x'], 0.001);
        $this->assertEqualsWithDelta(8.0, $pos[2]['y'], 0.001);

        // Slot 3: first column, second row → y advances by card_h + v_spacing.
        $this->assertEqualsWithDelta(13.0, $pos[3]['x'], 0.001);
        $this->assertEqualsWithDelta(8.0 + 53.98 + 2.775, $pos[3]['y'], 0.001);

        // Slot 10 (last): second column, fifth row.
        $this->assertEqualsWithDelta(13.0 + 85.6 + 12.8, $pos[10]['x'], 0.001);
        $this->assertEqualsWithDelta(8.0 + 4 * (53.98 + 2.775), $pos[10]['y'], 0.001);
    }

    public function test_layout_fits_within_a4(): void
    {
        $sheet = $this->seedSheet();
        $pos = $sheet->slotPositions();

        // Every slot's card must sit fully inside the 210×297 sheet.
        foreach ($pos as $p) {
            $this->assertLessThanOrEqual(210.0, $p['x'] + $sheet->card_w_mm + 0.001);
            $this->assertLessThanOrEqual(297.0, $p['y'] + $sheet->card_h_mm + 0.001);
        }
    }

    public function test_renders_a4_sheet_pdf_for_branded_cards(): void
    {
        $sheet = $this->seedSheet();

        $school = School::create([
            'name' => 'Alpha', 'code' => 'ALPHA', 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 100, 'max_teachers' => 10,
            'hmac_secret' => 'secret', 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);

        $s1 = Student::withoutGlobalScope('school')->create([
            'school_id' => $school->id, 'uid' => 'ALPHA001',
            'first_name' => 'Abebe', 'last_name' => 'Test', 'gender' => 'male',
            'status' => 'active', 'enrollment_date' => today(),
        ]);
        $s2 = Student::withoutGlobalScope('school')->create([
            'school_id' => $school->id, 'uid' => 'ALPHA002',
            'first_name' => 'Bekele', 'last_name' => 'Test', 'gender' => 'male',
            'status' => 'active', 'enrollment_date' => today(),
        ]);

        $assignments = [
            ['slot' => 1, 'student' => $s1],
            ['slot' => 2, 'student' => $s2],
        ];

        $bytes = app(SheetPrintService::class)->render($sheet, null, $school, $assignments, 'front');

        // A valid, non-trivial PDF was produced.
        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertGreaterThan(1000, strlen($bytes));

        // Tokens were minted for the QR codes as a side effect.
        $this->assertNotEmpty($s1->fresh()->qr_token);
        $this->assertNotEmpty($s2->fresh()->qr_token);
    }
}
