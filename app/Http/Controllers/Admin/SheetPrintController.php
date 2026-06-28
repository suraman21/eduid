<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\SheetSlotException;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CardOrder;
use App\Models\CardTemplate;
use App\Models\PrintSheet;
use App\Models\SheetTemplate;
use App\Models\Student;
use App\Services\SheetPrintService;
use App\Services\SheetTrackingService;
use App\Support\CardPreview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Professional multi-up sheet printing wizard (super-admin only). Lets the
 * operator place an order's students onto a physical PVC sheet, choosing the
 * sheet template, side(s), and a sequential block of empty slots, then renders
 * pixel-perfect front/back PDFs and records the slot occupancy.
 */
class SheetPrintController extends Controller
{
    public function __construct(
        private SheetTrackingService $tracking,
        private SheetPrintService $printer,
    ) {}

    /** Wizard entry, scoped to a card order. */
    public function wizard(CardOrder $cardOrder)
    {
        $cardOrder->load('school');
        $school = $cardOrder->school;

        $template = CardTemplate::where('school_id', $school->id)
            ->where('template_type', 'student')
            ->where('is_active', true)
            ->with('fields')
            ->first();

        $students = Student::whereIn('id', $cardOrder->student_ids ?? [])
            ->with('schoolClass')
            ->orderBy('first_name')
            ->get();

        $sheetTemplates = SheetTemplate::where('is_active', true)->get()
            ->map(fn (SheetTemplate $t) => $this->sheetTemplatePayload($t));

        // Open sheets for this school the operator can resume.
        $openSheets = PrintSheet::with('slots')
            ->where('status', 'open')
            ->where('school_id', $school->id)
            ->latest()
            ->get()
            ->map(fn (PrintSheet $s) => $this->sheetStatePayload($s));

        return Inertia::render('Admin/SheetPrint/Wizard', [
            'order'    => [
                'id'         => $cardOrder->id,
                'order_code' => $cardOrder->order_code,
            ],
            'school'   => [
                'id'            => $school->id,
                'name'          => $school->name,
                'code'          => $school->code,
                'academic_year' => $school->academic_year,
            ],
            'template'       => $template ? CardPreview::payload($school, $template) : null,
            'students'       => $students->map(fn (Student $s) => [
                'id'            => $s->id,
                'full_name'     => $s->full_name,
                'full_name_am'  => $s->full_name_am,
                'uid'           => $s->uid,
                'class_name'    => $s->schoolClass?->display_name,
                'date_of_birth' => $s->date_of_birth?->format('M d, Y'),
                'blood_type'    => $s->blood_type,
                'has_photo'     => $s->hasPhoto(),
                'photo_url'     => $s->hasPhoto() ? route('admin.card-orders.student-photo', [$cardOrder, $s]) : null,
                'qr_svg'        => CardPreview::qrSvg($s->uid.'|'.($s->qr_token ?? '')),
            ])->values(),
            'sheetTemplates' => $sheetTemplates->values(),
            'openSheets'     => $openSheets->values(),
        ]);
    }

    /**
     * Validate the selection, render the requested side(s) to private PDFs, and
     * record the slot occupancy. Returns JSON with download URLs the wizard then
     * opens. Front and back are SEPARATE PDFs in the same slot order.
     */
    public function generate(Request $request, CardOrder $cardOrder)
    {
        $data = $request->validate([
            'sheet_template_id' => ['required', Rule::exists('sheet_templates', 'id')],
            'print_sheet_id'    => ['nullable', Rule::exists('print_sheets', 'id')],
            'side'              => ['required', Rule::in(['front', 'back', 'both'])],
            'assignments'                 => ['required', 'array', 'min:1'],
            'assignments.*.slot'          => ['required', 'integer', 'min:1'],
            'assignments.*.student_id'    => ['required', 'integer'],
        ]);

        $cardOrder->load('school');
        $school = $cardOrder->school;
        $sheetTemplate = SheetTemplate::findOrFail($data['sheet_template_id']);

        // Every student must belong to this order — no cross-order leakage.
        $orderStudentIds = array_map('intval', $cardOrder->student_ids ?? []);
        foreach ($data['assignments'] as $a) {
            if (! in_array((int) $a['student_id'], $orderStudentIds, true)) {
                return response()->json(['message' => 'A selected student is not part of this order.'], 422);
            }
        }

        $cardTemplate = CardTemplate::where('school_id', $school->id)
            ->where('template_type', 'student')
            ->where('is_active', true)
            ->with('fields')
            ->first();

        // Resume an open sheet or open a fresh one.
        $sheet = ! empty($data['print_sheet_id'])
            ? PrintSheet::where('status', 'open')->findOrFail($data['print_sheet_id'])
            : $this->tracking->openSheet($sheetTemplate, $school, $request->user());

        // Mark occupancy first — this enforces the sequential rule under a lock
        // and fails cleanly before we spend time rendering a PDF.
        try {
            $sheet = $this->tracking->assign($sheet, $data['assignments'], $data['side'], $cardOrder->id);
        } catch (SheetSlotException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Build the slot→student map for the renderer.
        $students = Student::whereIn('id', array_column($data['assignments'], 'student_id'))
            ->with('schoolClass')
            ->get()
            ->keyBy('id');

        $renderAssignments = [];
        foreach ($data['assignments'] as $a) {
            if ($student = $students->get((int) $a['student_id'])) {
                $renderAssignments[] = ['slot' => (int) $a['slot'], 'student' => $student];
            }
        }

        $urls = [];
        $sides = $data['side'] === 'both' ? ['front', 'back'] : [$data['side']];
        foreach ($sides as $side) {
            $bytes = $this->printer->render($sheetTemplate, $cardTemplate, $school, $renderAssignments, $side);
            $path = "print_sheets/{$sheet->id}/{$side}/sheet-".now()->format('Ymd-His').'.pdf';
            Storage::disk('local')->put($path, $bytes);
            $urls[$side] = route('admin.sheet-print.download', ['printSheet' => $sheet->id, 'side' => $side]);
        }

        AuditLog::createRecord(
            $school->id, $request->user()->id,
            'sheet_print.generated', 'print_sheets', $sheet->id, null,
            ['order_code' => $cardOrder->order_code, 'side' => $data['side'], 'slots' => array_column($data['assignments'], 'slot')]
        );

        return response()->json([
            'sheet' => $this->sheetStatePayload($sheet->fresh()->load('slots')),
            'urls'  => $urls,
        ]);
    }

    /** Stream the most recent PDF for a sheet side (private disk, super-admin only). */
    public function download(PrintSheet $printSheet, string $side)
    {
        abort_unless(in_array($side, ['front', 'back'], true), 404);

        $dir = "print_sheets/{$printSheet->id}/{$side}";
        $files = collect(Storage::disk('local')->files($dir))->sort()->values();
        abort_if($files->isEmpty(), 404, 'No print file generated yet.');

        return Storage::disk('local')->download(
            $files->last(),
            "sheet-{$printSheet->id}-{$side}.pdf"
        );
    }

    /** Geometry payload for the accurate A4 preview (mm, matches the PDF). */
    private function sheetTemplatePayload(SheetTemplate $t): array
    {
        return [
            'id'         => $t->id,
            'key'        => $t->key,
            'name'       => $t->name,
            'sheet_w_mm' => $t->sheet_w_mm,
            'sheet_h_mm' => $t->sheet_h_mm,
            'card_w_mm'  => $t->card_w_mm,
            'card_h_mm'  => $t->card_h_mm,
            'columns'    => $t->columns,
            'rows'       => $t->rows,
            'slot_count' => $t->slotCount(),
            'positions'  => $t->slotPositions(),
        ];
    }

    /** Current slot occupancy for the grid. */
    private function sheetStatePayload(PrintSheet $sheet): array
    {
        return [
            'id'                => $sheet->id,
            'sheet_template_id' => $sheet->sheet_template_id,
            'status'            => $sheet->status,
            'next_empty_slot'   => $sheet->nextEmptySlot(),
            'slots'             => $sheet->slots->map(fn ($s) => [
                'slot_number' => $s->slot_number,
                'status'      => $s->status,
                'student_id'  => $s->student_id,
            ])->values(),
        ];
    }
}
