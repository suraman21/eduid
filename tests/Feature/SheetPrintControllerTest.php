<?php

namespace Tests\Feature;

use App\Models\CardOrder;
use App\Models\PrintSheet;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\SheetTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SheetPrintControllerTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        (new SheetTemplateSeeder)->run();

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
        $this->superAdmin = User::create([
            'name' => 'Super', 'email' => 'super@test.com', 'password' => Hash::make('password'),
            'role' => 'super_admin', 'school_id' => null, 'is_active' => true,
        ]);
    }

    private function student(string $first): Student
    {
        return Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => 'ALPHA'.strtoupper($first),
            'first_name' => $first, 'last_name' => 'Test', 'gender' => 'male',
            'status' => 'active', 'enrollment_date' => today(),
        ]);
    }

    private function order(array $students): CardOrder
    {
        $this->actingAs($this->admin)->post('/school/card-orders', [
            'order_type' => 'annual_renewal',
            'student_ids' => array_map(fn ($s) => $s->id, $students),
        ]);

        return CardOrder::withoutGlobalScope('school')->first();
    }

    public function test_super_admin_can_open_the_wizard(): void
    {
        $s1 = $this->student('Abebe');
        $order = $this->order([$s1]);

        $this->actingAs($this->superAdmin)
            ->get("/admin/card-orders/{$order->id}/sheet-print")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/SheetPrint/Wizard', false)
                ->has('students', 1)
                ->has('sheetTemplates', 2));
    }

    public function test_school_admin_cannot_open_the_wizard(): void
    {
        $s1 = $this->student('Abebe');
        $order = $this->order([$s1]);

        $this->actingAs($this->admin)
            ->get("/admin/card-orders/{$order->id}/sheet-print")
            ->assertForbidden();
    }

    public function test_generate_creates_sheet_marks_slots_and_stores_pdf(): void
    {
        $s1 = $this->student('Abebe');
        $s2 = $this->student('Bekele');
        $order = $this->order([$s1, $s2]);

        $response = $this->actingAs($this->superAdmin)->postJson(
            "/admin/card-orders/{$order->id}/sheet-print/generate",
            [
                'sheet_template_id' => \App\Models\SheetTemplate::where('key', 'a4-10up')->first()->id,
                'print_sheet_id' => null,
                'side' => 'front',
                'assignments' => [
                    ['slot' => 1, 'student_id' => $s1->id],
                    ['slot' => 2, 'student_id' => $s2->id],
                ],
            ]
        );

        $response->assertOk();
        $response->assertJsonStructure(['sheet' => ['id', 'slots'], 'urls' => ['front']]);

        $sheet = PrintSheet::firstOrFail();
        $this->assertSame('printed', $sheet->slots()->where('slot_number', 1)->first()->status);
        $this->assertSame($s1->id, $sheet->slots()->where('slot_number', 1)->first()->student_id);
        $this->assertSame(3, $sheet->nextEmptySlot());

        // A PDF landed on the private disk.
        $files = Storage::disk('local')->files("print_sheets/{$sheet->id}/front");
        $this->assertNotEmpty($files);
    }

    public function test_generate_rejects_non_sequential_slots(): void
    {
        $s1 = $this->student('Abebe');
        $order = $this->order([$s1]);

        $this->actingAs($this->superAdmin)->postJson(
            "/admin/card-orders/{$order->id}/sheet-print/generate",
            [
                'sheet_template_id' => \App\Models\SheetTemplate::where('key', 'a4-10up')->first()->id,
                'side' => 'front',
                'assignments' => [['slot' => 3, 'student_id' => $s1->id]],
            ]
        )->assertStatus(422);
    }

    public function test_generate_rejects_student_outside_order(): void
    {
        $s1 = $this->student('Abebe');
        $foreign = $this->student('Outsider');
        $order = $this->order([$s1]);

        $this->actingAs($this->superAdmin)->postJson(
            "/admin/card-orders/{$order->id}/sheet-print/generate",
            [
                'sheet_template_id' => \App\Models\SheetTemplate::where('key', 'a4-10up')->first()->id,
                'side' => 'front',
                'assignments' => [['slot' => 1, 'student_id' => $foreign->id]],
            ]
        )->assertStatus(422);
    }

    public function test_download_streams_the_generated_pdf(): void
    {
        $s1 = $this->student('Abebe');
        $order = $this->order([$s1]);
        $tplId = \App\Models\SheetTemplate::where('key', 'a4-10up')->first()->id;

        $this->actingAs($this->superAdmin)->postJson(
            "/admin/card-orders/{$order->id}/sheet-print/generate",
            ['sheet_template_id' => $tplId, 'side' => 'front', 'assignments' => [['slot' => 1, 'student_id' => $s1->id]]]
        )->assertOk();

        $sheet = PrintSheet::firstOrFail();
        $this->actingAs($this->superAdmin)
            ->get("/admin/print-sheets/{$sheet->id}/download/front")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
