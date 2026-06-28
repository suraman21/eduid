<?php

namespace Tests\Feature;

use App\Exceptions\SheetSlotException;
use App\Models\PrintSheet;
use App\Models\School;
use App\Models\SheetTemplate;
use App\Models\Student;
use App\Services\SheetTrackingService;
use Database\Seeders\SheetTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SheetTrackingTest extends TestCase
{
    use RefreshDatabase;

    private SheetTemplate $sheet;
    private School $school;
    private SheetTrackingService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        (new SheetTemplateSeeder)->run();
        $this->sheet = SheetTemplate::where('key', 'a4-10up')->firstOrFail();
        $this->school = School::create([
            'name' => 'Alpha', 'code' => 'ALPHA', 'is_active' => true,
            'plan_tier' => 'premium', 'max_students' => 100, 'max_teachers' => 10,
            'hmac_secret' => 'secret', 'academic_year' => '2018 E.C.',
            'subscription_status' => 'active',
        ]);
        $this->svc = app(SheetTrackingService::class);
    }

    private function student(string $uid): Student
    {
        return Student::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'uid' => $uid,
            'first_name' => $uid, 'last_name' => 'Test', 'gender' => 'male',
            'status' => 'active', 'enrollment_date' => today(),
        ]);
    }

    public function test_open_sheet_creates_one_empty_slot_per_position(): void
    {
        $sheet = $this->svc->openSheet($this->sheet, $this->school);

        $this->assertSame(10, $sheet->slots()->count());
        $this->assertSame(10, $sheet->slots()->where('status', 'empty')->count());
        $this->assertSame(1, $sheet->nextEmptySlot());
        $this->assertFalse($sheet->isFull());
    }

    public function test_sequential_fill_from_slot_1_is_allowed(): void
    {
        $sheet = $this->svc->openSheet($this->sheet, $this->school);

        $this->svc->validateSequential($sheet, [1, 2, 3]);
        $this->assertTrue(true); // no exception
    }

    public function test_non_contiguous_selection_is_rejected(): void
    {
        $sheet = $this->svc->openSheet($this->sheet, $this->school);

        $this->expectException(SheetSlotException::class);
        $this->svc->validateSequential($sheet, [1, 3]);
    }

    public function test_starting_mid_sheet_with_empty_prefix_is_rejected(): void
    {
        $sheet = $this->svc->openSheet($this->sheet, $this->school);

        // Slots 1-2 are still empty, so starting at 5 is illegal.
        $this->expectException(SheetSlotException::class);
        $this->svc->validateSequential($sheet, [5]);
    }

    public function test_assign_marks_slots_printed_and_persists_students(): void
    {
        $sheet = $this->svc->openSheet($this->sheet, $this->school);
        $s1 = $this->student('A1');
        $s2 = $this->student('A2');

        $sheet = $this->svc->assign($sheet, [
            ['slot' => 1, 'student_id' => $s1->id],
            ['slot' => 2, 'student_id' => $s2->id],
        ], 'both');

        $slot1 = $sheet->slots()->where('slot_number', 1)->first();
        $this->assertSame('printed', $slot1->status);
        $this->assertSame($s1->id, $slot1->student_id);
        $this->assertTrue($slot1->printed_front);
        $this->assertTrue($slot1->printed_back);

        // Next card now goes to slot 3 — the sheet can be reopened and topped up.
        $this->assertSame(3, $sheet->nextEmptySlot());
    }

    public function test_partial_sheet_resumes_from_next_empty_slot(): void
    {
        $sheet = $this->svc->openSheet($this->sheet, $this->school);
        $this->svc->assign($sheet, [
            ['slot' => 1, 'student_id' => $this->student('B1')->id],
            ['slot' => 2, 'student_id' => $this->student('B2')->id],
            ['slot' => 3, 'student_id' => $this->student('B3')->id],
            ['slot' => 4, 'student_id' => $this->student('B4')->id],
        ], 'both');

        $sheet->refresh();
        $this->assertSame(5, $sheet->nextEmptySlot());

        // Now printing may begin from slot 5 (per the spec example).
        $this->svc->validateSequential($sheet, [5, 6]);
        $sheet = $this->svc->assign($sheet, [
            ['slot' => 5, 'student_id' => $this->student('B5')->id],
            ['slot' => 6, 'student_id' => $this->student('B6')->id],
        ], 'both');

        $this->assertSame(6, $sheet->printedCount());
        $this->assertSame(7, $sheet->nextEmptySlot());
    }

    public function test_full_sheet_auto_completes_and_rejects_more(): void
    {
        $sheet = $this->svc->openSheet($this->sheet, $this->school);
        $items = [];
        for ($i = 1; $i <= 10; $i++) {
            $items[] = ['slot' => $i, 'student_id' => $this->student("C{$i}")->id];
        }
        $sheet = $this->svc->assign($sheet, $items, 'both');

        $this->assertTrue($sheet->isFull());
        $this->assertSame('completed', $sheet->fresh()->status);

        $this->expectException(SheetSlotException::class);
        $this->svc->validateSequential($sheet->fresh(), [1]);
    }

    public function test_assign_rejects_non_sequential_under_lock(): void
    {
        $sheet = $this->svc->openSheet($this->sheet, $this->school);

        $this->expectException(SheetSlotException::class);
        $this->svc->assign($sheet, [
            ['slot' => 2, 'student_id' => $this->student('D2')->id],
        ], 'both');
    }
}
