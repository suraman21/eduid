<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CardOrder;
use App\Models\CardTemplate;
use App\Models\Student;
use App\Services\CardPrintService;
use App\Services\QrTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use TCPDF2DBarcode;

/**
 * Super-admin processing of card orders. The admin enters the confirmation
 * code a school shared, reviews it, confirms it, then walks the order through
 * the fulfilment lifecycle (confirmed → printing → shipped → delivered).
 *
 * CardOrder uses the BelongsToSchool scope, but a super_admin has no school_id
 * so the scope is a no-op for this role — these queries see every school.
 */
class CardOrderController extends Controller
{
    /** Forward-only fulfilment transitions an admin may apply. */
    private const TRANSITIONS = [
        'confirmed' => 'printing',
        'printing'  => 'shipped',
        'shipped'   => 'delivered',
    ];

    public function index(Request $request)
    {
        $query = CardOrder::with(['school', 'orderedBy']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($code = $request->get('code')) {
            $query->where('order_code', 'like', "%{$code}%");
        }

        $orders = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return Inertia::render('Admin/CardOrders/Index', [
            'orders'  => $orders->through(fn ($o) => [
                'id'            => $o->id,
                'order_code'    => $o->order_code,
                'school_name'   => $o->school?->name,
                'order_type'    => $o->order_type,
                'student_count' => $o->student_count,
                'amount_etb'    => (float) $o->amount_etb,
                'status'        => $o->status,
            ]),
            'filters' => $request->only('code', 'status'),
        ]);
    }

    /**
     * Enter-a-code screen. If a code is supplied, resolve and show it.
     */
    public function process(Request $request)
    {
        $order = null;
        $code  = trim((string) $request->get('code', ''));

        if ($code !== '') {
            $order = CardOrder::with(['school', 'orderedBy'])
                ->where('order_code', $code)
                ->first();
        }

        $students     = $order ? $order->getStudents()->load('schoolClass') : collect();
        $noPhotoCount = $students->filter(fn ($s) => ! $s->hasPhoto())->count();

        return Inertia::render('Admin/CardOrders/Process', [
            'code'          => $code,
            'order'         => $order ? [
                'id'            => $order->id,
                'order_code'    => $order->order_code,
                'school_name'   => $order->school?->name,
                'ordered_by'    => $order->orderedBy?->name,
                'order_type'    => $order->order_type,
                'student_count' => $order->student_count,
                'amount_etb'    => (float) $order->amount_etb,
                'status'        => $order->status,
            ] : null,
            'students'      => $students->map(fn ($s) => [
                'id'        => $s->id,
                'full_name' => $s->full_name,
                'uid'       => $s->uid,
                'has_photo' => $s->hasPhoto(),
            ]),
            'no_photo_count' => $noPhotoCount,
        ]);
    }

    public function show(CardOrder $cardOrder)
    {
        $cardOrder->load(['school', 'orderedBy']);
        $students = $cardOrder->getStudents()->load('schoolClass');

        return Inertia::render('Admin/CardOrders/Show', [
            'order'    => [
                'id'              => $cardOrder->id,
                'order_code'      => $cardOrder->order_code,
                'school_name'     => $cardOrder->school?->name,
                'ordered_by'      => $cardOrder->orderedBy?->name,
                'order_type'      => $cardOrder->order_type,
                'student_count'   => $cardOrder->student_count,
                'amount_etb'      => (float) $cardOrder->amount_etb,
                'status'          => $cardOrder->status,
                'cancel_reason'   => $cardOrder->cancel_reason,
                'print_file_path' => $cardOrder->print_file_path,
                'can_cancel'      => $cardOrder->canBeCancelledBy(auth()->user()),
                'created_at'      => $cardOrder->created_at->toISOString(),
            ],
            'students' => $students->map(fn ($s) => [
                'id'        => $s->id,
                'full_name' => $s->full_name,
                'uid'       => $s->uid,
                'has_photo' => $s->hasPhoto(),
            ]),
        ]);
    }

    /**
     * Confirm a pending order and generate the high-resolution, print-ready PDF
     * (TCPDF, server-side only, PRD §03). The file lands under
     * storage/app/print_jobs/{order_code}/ — a private path never exposed via a
     * web URL. Only the Super Admin can later download it.
     */
    public function confirm(CardOrder $cardOrder, CardPrintService $printer)
    {
        if (! $cardOrder->isPending()) {
            return back()->with('error', 'Only pending orders can be confirmed.');
        }

        $path = $printer->generate($cardOrder);

        $cardOrder->update([
            'status'          => 'confirmed',
            'confirmed_at'    => now(),
            'print_file_path' => $path,
        ]);

        AuditLog::createRecord(
            $cardOrder->school_id, auth()->id(),
            'card_order.confirmed', 'card_orders', $cardOrder->id, null,
            ['order_code' => $cardOrder->order_code]
        );

        return back()->with('success', "Order {$cardOrder->order_code} confirmed — print file generated.");
    }

    /**
     * Re-generate the print-ready PDF (e.g. after photos were added or a
     * student detail changed). Available once an order is confirmed and before
     * it ships. Replaces the previous file for the order.
     */
    public function regeneratePrintFile(CardOrder $cardOrder, CardPrintService $printer)
    {
        if (! in_array($cardOrder->status, ['confirmed', 'printing'], true)) {
            return back()->with('error', 'Print files can only be regenerated for confirmed or printing orders.');
        }

        $path = $printer->generate($cardOrder);
        $cardOrder->update(['print_file_path' => $path]);

        AuditLog::createRecord(
            $cardOrder->school_id, auth()->id(),
            'card_order.print_regenerated', 'card_orders', $cardOrder->id, null,
            ['order_code' => $cardOrder->order_code]
        );

        return back()->with('success', 'Print file regenerated.');
    }

    /**
     * Stream the print-ready PDF to the Super Admin. The file lives on the
     * private disk and is never reachable by a /storage URL — this authenticated
     * admin route is the only way to retrieve it.
     */
    public function downloadPrintFile(CardOrder $cardOrder)
    {
        if (! $cardOrder->print_file_path
            || ! Storage::disk('local')->exists($cardOrder->print_file_path)
        ) {
            abort(404, 'No print file has been generated for this order yet.');
        }

        AuditLog::createRecord(
            $cardOrder->school_id, auth()->id(),
            'card_order.print_downloaded', 'card_orders', $cardOrder->id, null,
            ['order_code' => $cardOrder->order_code]
        );

        return Storage::disk('local')->download(
            $cardOrder->print_file_path,
            "{$cardOrder->order_code}-cards.pdf"
        );
    }

    /**
     * Advance the order one step along the fulfilment lifecycle.
     */
    public function updateStatus(Request $request, CardOrder $cardOrder)
    {
        $validated = $request->validate([
            'status'        => ['required', Rule::in(array_values(self::TRANSITIONS))],
            'tracking_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $expected = self::TRANSITIONS[$cardOrder->status] ?? null;

        if ($validated['status'] !== $expected) {
            return back()->with('error', "Cannot move a {$cardOrder->status} order to {$validated['status']}.");
        }

        $timestampColumn = [
            'printing'  => 'printed_at',
            'shipped'   => 'shipped_at',
            'delivered' => 'delivered_at',
        ][$validated['status']];

        $cardOrder->update([
            'status'          => $validated['status'],
            $timestampColumn  => now(),
            'tracking_note'   => $validated['tracking_note'] ?? $cardOrder->tracking_note,
        ]);

        AuditLog::createRecord(
            $cardOrder->school_id, auth()->id(),
            'card_order.status', 'card_orders', $cardOrder->id, null,
            ['order_code' => $cardOrder->order_code, 'status' => $validated['status']]
        );

        return back()->with('success', "Order marked {$validated['status']}.");
    }

    /**
     * Client-side print view. Renders every card in the order at exact card size
     * in the browser; the admin uses the browser's "Save as PDF" / print dialog.
     *
     * This generates NOTHING server-side — no stored file, no stale cache, no
     * download bandwidth — and always reflects the current template + student
     * data. It is the space/bandwidth-friendly path; the TCPDF route remains as a
     * fallback. Super-admin only (Golden Rule: schools never reach this).
     */
    public function printView(CardOrder $cardOrder, QrTokenService $qr)
    {
        $cardOrder->load('school');
        $school   = $cardOrder->school;
        $template = CardTemplate::where('school_id', $school->id)
            ->where('template_type', 'student')
            ->where('is_active', true)
            ->with('fields')
            ->first();

        $students = Student::whereIn('id', $cardOrder->student_ids ?? [])
            ->with('schoolClass')
            ->orderBy('first_name')
            ->get();

        // Make sure every student carries a valid QR token before we encode it.
        foreach ($students as $s) {
            if (empty($s->qr_token) || $s->qr_token_year !== $school->academic_year) {
                $qr->generate($s, $school);
                $s->refresh();
            }
        }

        AuditLog::createRecord(
            $school->id, auth()->id(),
            'card_order.print_view', 'card_orders', $cardOrder->id, null,
            ['order_code' => $cardOrder->order_code]
        );

        return Inertia::render('Admin/CardOrders/Print', [
            'order'    => [
                'id'         => $cardOrder->id,
                'order_code' => $cardOrder->order_code,
                'count'      => $students->count(),
            ],
            'school'   => ['name' => $school->name, 'code' => $school->code, 'academic_year' => $school->academic_year],
            'template' => $template ? $this->templatePayload($school, $template) : null,
            'cards'    => $students->map(fn (Student $s) => [
                'full_name'     => $s->full_name,
                'full_name_am'  => $s->full_name_am,
                'uid'           => $s->uid,
                'class_name'    => $s->schoolClass?->display_name,
                'date_of_birth' => $s->date_of_birth?->format('M d, Y'),
                'blood_type'    => $s->blood_type,
                'photo_url'     => $s->hasPhoto() ? route('admin.card-orders.student-photo', [$cardOrder, $s]) : null,
                'qr_svg'        => $this->qrSvg($s->uid.'|'.($s->qr_token ?? '')),
            ])->values(),
        ]);
    }

    /**
     * Serve one student's photo for the print view, scoped to the order's school.
     */
    public function studentPhoto(CardOrder $cardOrder, Student $student)
    {
        abort_unless(
            $student->school_id === $cardOrder->school_id
                && in_array($student->id, $cardOrder->student_ids ?? [], true),
            404
        );

        if (! $student->photo_path) {
            abort(404);
        }
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($student->photo_path)) {
                return Storage::disk($disk)->response($student->photo_path, null, [
                    'Cache-Control' => 'private, max-age=300',
                ]);
            }
        }
        abort(404);
    }

    /**
     * Shape a template into the px-coordinate payload the shared TemplateCard
     * renderer consumes. Background images are served from the existing
     * admin-gated template-image route (no base64 bloat).
     */
    private function templatePayload($school, CardTemplate $template): array
    {
        $front = "card_previews/{$school->id}/front.png";
        $back  = "card_previews/{$school->id}/back.png";

        $frontUrl = Storage::disk('public')->exists($front)
            ? Storage::disk('public')->url($front)
            : (Storage::disk('local')->exists("cards/{$school->id}/front.png")
                ? route('admin.card-template.image', [$school, 'front']) : null);
        $backUrl = Storage::disk('public')->exists($back)
            ? Storage::disk('public')->url($back)
            : (Storage::disk('local')->exists("cards/{$school->id}/back.png")
                ? route('admin.card-template.image', [$school, 'back']) : null);

        $backInfo = Storage::disk('local')->exists("cards/{$school->id}/back.png")
            ? @getimagesize(Storage::disk('local')->path("cards/{$school->id}/back.png")) : null;

        return [
            'frontUrl' => $frontUrl,
            'backUrl'  => $backUrl,
            'canvasW'  => $template->canvasW(),
            'canvasH'  => $template->canvasH(),
            'backW'    => $backInfo[0] ?? $template->canvasW(),
            'backH'    => $backInfo[1] ?? $template->canvasH(),
            'cardWmm'  => $template->cardWMm(),
            'cardHmm'  => $template->cardHMm(),
            'fields'   => $template->fields->map(fn ($f) => [
                'field_type'    => $f->field_type,
                'field_key'     => $f->field_key,
                'side'          => $f->side ?? 'front',
                'x'             => $f->px_x,
                'y'             => $f->px_y,
                'w'             => $f->px_w ?? 0,
                'h'             => $f->px_h ?? 0,
                'font_size'     => $f->font_size,
                'font_color'    => $f->font_color,
                'font_family'   => $f->font_family,
                'text_align'    => $f->text_align,
                'font_weight'   => $f->font_weight,
                'border_radius' => $f->border_radius ?? 0,
                'custom_value'  => $f->custom_value,
            ])->values()->all(),
        ];
    }

    /** Inline QR SVG (TCPDF 2D barcode engine — CSP-safe, vector, crisp in print). */
    private function qrSvg(string $data): string
    {
        $grid = (new TCPDF2DBarcode($data, 'QRCODE,M'))->getBarcodeArray();
        if (empty($grid['bcode'])) {
            return '';
        }
        $rows = (int) $grid['num_rows'];
        $cols = (int) $grid['num_cols'];
        $path = '';
        foreach ($grid['bcode'] as $y => $row) {
            foreach ($row as $x => $cell) {
                if ($cell) {
                    $path .= "M{$x},{$y}h1v1h-1Z";
                }
            }
        }
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" shape-rendering="crispEdges" style="width:100%%;height:100%%">'
            .'<rect width="%d" height="%d" fill="white"/><path d="%s" fill="black"/></svg>',
            $cols, $rows, $cols, $rows, $path
        );
    }

    public function cancel(Request $request, CardOrder $cardOrder)
    {
        if (! $cardOrder->canBeCancelledBy(auth()->user())) {
            return back()->with('error', 'This order can no longer be cancelled.');
        }

        $validated = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $cardOrder->update([
            'status'        => 'cancelled',
            'cancelled_at'  => now(),
            'cancel_reason' => $validated['cancel_reason'] ?? 'Cancelled by Arkeon.',
        ]);

        AuditLog::createRecord(
            $cardOrder->school_id, auth()->id(),
            'card_order.cancelled', 'card_orders', $cardOrder->id, null,
            ['order_code' => $cardOrder->order_code]
        );

        return back()->with('success', "Order {$cardOrder->order_code} cancelled.");
    }
}
