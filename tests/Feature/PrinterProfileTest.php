<?php

namespace Tests\Feature;

use App\Models\PrinterProfile;
use App\Models\School;
use App\Models\SheetTemplate;
use App\Models\User;
use Database\Seeders\SheetTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PrinterProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $schoolAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        (new SheetTemplateSeeder)->run();

        $school = School::create([
            'name' => 'Alpha', 'code' => 'ALPHA', 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 100, 'max_teachers' => 10,
            'hmac_secret' => 'secret', 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);
        $this->superAdmin = User::create([
            'name' => 'Super', 'email' => 'super@test.com', 'password' => Hash::make('password'),
            'role' => 'super_admin', 'school_id' => null, 'is_active' => true,
        ]);
        $this->schoolAdmin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_SCHOOL_ADMIN, 'school_id' => $school->id, 'is_active' => true,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Epson L3250',
            'x_offset_front_mm' => 0, 'y_offset_front_mm' => 0,
            'x_offset_back_mm' => 1.2, 'y_offset_back_mm' => 0.8,
            'rotation_offset_deg' => 0, 'mirror_back' => false,
            'flip_mode' => 'long_edge', 'is_default' => true,
        ], $overrides);
    }

    public function test_super_admin_can_create_printer_profile(): void
    {
        $this->actingAs($this->superAdmin)
            ->post('/admin/printers', $this->payload())
            ->assertRedirect();

        $p = PrinterProfile::firstOrFail();
        $this->assertSame('Epson L3250', $p->name);
        $this->assertEqualsWithDelta(1.2, $p->x_offset_back_mm, 0.001);
        $this->assertTrue($p->is_default);
    }

    public function test_school_admin_cannot_manage_printers(): void
    {
        $this->actingAs($this->schoolAdmin)->get('/admin/printers')->assertForbidden();
        $this->actingAs($this->schoolAdmin)->post('/admin/printers', $this->payload())->assertForbidden();
    }

    public function test_setting_default_unsets_previous_default(): void
    {
        $this->actingAs($this->superAdmin)->post('/admin/printers', $this->payload());
        $first = PrinterProfile::firstOrFail();

        $this->actingAs($this->superAdmin)->post('/admin/printers', $this->payload([
            'name' => 'Canon G3410', 'is_default' => true,
        ]));

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue(PrinterProfile::where('name', 'Canon G3410')->first()->is_default);
    }

    public function test_calibration_sheet_downloads_for_both_sides(): void
    {
        $this->actingAs($this->superAdmin)->post('/admin/printers', $this->payload());
        $printer = PrinterProfile::firstOrFail();
        $sheet = SheetTemplate::where('key', 'a4-10up')->firstOrFail();

        foreach (['front', 'back'] as $side) {
            $response = $this->actingAs($this->superAdmin)
                ->get("/admin/printers/{$printer->id}/calibration/{$sheet->id}/{$side}");

            $response->assertOk();
            $response->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $response->streamedContent ?? $response->getContent());
        }
    }

    public function test_flip_instruction_matches_flip_mode(): void
    {
        $book = new PrinterProfile(['flip_mode' => 'long_edge']);
        $calendar = new PrinterProfile(['flip_mode' => 'short_edge']);

        $this->assertStringContainsString('BOOK', $book->flipInstruction());
        $this->assertStringContainsString('CALENDAR', $calendar->flipInstruction());
    }
}
