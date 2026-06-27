<?php

namespace App\Http\Controllers\School;

use App\Models\CardOrder;
use App\Models\CardTemplate;
use App\Models\School;
use App\Models\Student;
use App\Services\QrTokenService;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use TCPDF2DBarcode;

/**
 * Web-viewable student ID cards (no server-side PDF — PRD Golden Rule).
 * Browser print produces physical-card-sized output via CSS @page rules.
 */
class IdCardController extends BaseSchoolController
{
    public function __construct(private QrTokenService $qr) {}

    /**
     * Preview a single student's ID card.
     */
    public function show(Student $student)
    {
        if ($student->class_id !== null) {
            $this->requireTeacherCanAccessClass($student->class_id);
        }

        $school = $this->currentSchool();
        $student->load('schoolClass');

        // Mint a valid token BEFORE building the QR code, otherwise the card
        // renders with a stale/empty payload that won't scan (BUG-008).
        $this->ensureToken($student, $school);

        $qrSvg = $this->qrSvg($this->qrPayload($student));

        return Inertia::render('School/IdCards/Show', [
            'student'  => $this->cardStudent($student, $qrSvg),
            'school'   => $this->cardSchool($school),
            'template' => $this->cardTemplate($school),
        ]);
    }

    /**
     * Batch preview of all cards in a card order — for print-before-ordering review.
     */
    public function batch(CardOrder $cardOrder)
    {
        $this->requireAdmin();

        $school = $this->currentSchool();
        $students = Student::with('schoolClass')
            ->whereIn('id', $cardOrder->student_ids ?? [])
            ->orderBy('first_name')
            ->get()
            ->map(function (Student $s) use ($school) {
                // Ensure the token exists before the QR payload is assembled.
                $this->ensureToken($s, $school);

                return $this->cardStudent($s, $this->qrSvg($this->qrPayload($s)));
            })
            ->values();

        return Inertia::render('School/IdCards/Batch', [
            'students'  => $students,
            'school'    => $this->cardSchool($school),
            'template'  => $this->cardTemplate($school),
            'cardOrder' => [
                'id'    => $cardOrder->id,
                'code'  => $cardOrder->order_code,
                'count' => $students->count(),
            ],
        ]);
    }

    /**
     * Build the active card template payload for the school, or null when none is
     * configured (the React side then falls back to the built-in branded layout).
     *
     * Coordinates are emitted in canvas pixels — the same space the super-admin
     * designer positions fields in — so the on-screen preview matches the printed
     * card field-for-field. The background is served from the public preview disk
     * the designer already writes to (low-res, watermarked on screen per the
     * Golden Rule — never the print-ready artwork).
     */
    private function cardTemplate(School $school): ?array
    {
        $template = CardTemplate::where('school_id', $school->id)
            ->where('template_type', 'student')
            ->where('is_active', true)
            ->with('fields')
            ->first();

        if (! $template) {
            return null;
        }

        // Resolve each side's background URL. Prefer the fast public-disk preview the
        // designer writes to; if that copy is absent (e.g. storage:link missing on
        // shared hosting, or a template uploaded before the public write existed),
        // fall back to a tenant-scoped private-disk route so the preview still works.
        $frontUrl = $this->templateImageUrl($school, 'front');
        $backUrl  = $this->templateImageUrl($school, 'back');

        // Without a usable background image there is nothing to overlay — fall back.
        if (! $frontUrl) {
            return null;
        }

        $backLocal = "cards/{$school->id}/back.png";
        $backInfo  = $backUrl && Storage::disk('local')->exists($backLocal)
            ? @getimagesize(Storage::disk('local')->path($backLocal))
            : null;
        [$backW, $backH] = $backInfo ? [$backInfo[0], $backInfo[1]] : [$template->canvasW(), $template->canvasH()];

        return [
            'frontUrl' => $frontUrl,
            'backUrl'  => $backUrl,
            'canvasW'  => $template->canvasW(),
            'canvasH'  => $template->canvasH(),
            'backW'    => $backW,
            'backH'    => $backH,
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

    /**
     * Resolve a usable URL for one side of the school's card-template background,
     * or null when no image exists on either disk. Public preview first (fast,
     * static), then a tenant-scoped private-disk route as a fallback.
     */
    private function templateImageUrl(School $school, string $side): ?string
    {
        // The private-disk copy is the designer's authoritative write and needs no
        // storage:link symlink, so it is the reliable source on shared hosting.
        if (Storage::disk('local')->exists("cards/{$school->id}/{$side}.png")) {
            return route('school.id-card.template-image', $side);
        }
        if (Storage::disk('public')->exists("card_previews/{$school->id}/{$side}.png")) {
            return Storage::disk('public')->url("card_previews/{$school->id}/{$side}.png");
        }
        return null;
    }

    /**
     * Stream the current school's own card-template background from the private
     * disk. Tenant-scoped: a school can only ever read its own template image.
     */
    public function templateImage(string $side)
    {
        abort_unless(in_array($side, ['front', 'back'], true), 404);

        $school = $this->currentSchool();
        $path = "cards/{$school->id}/{$side}.png";
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=60',
            'Content-Type'  => 'image/png',
        ]);
    }

    /**
     * Shape a student into the flat card payload the React preview consumes.
     */
    private function cardStudent(Student $student, string $qrSvg): array
    {
        return [
            'id'            => $student->id,
            'full_name'     => $student->full_name,
            'full_name_am'  => $student->full_name_am,
            'uid'           => $student->uid,
            'photo_url'     => $student->photo_url,
            'class_name'    => $student->schoolClass?->display_name,
            'date_of_birth' => $student->date_of_birth?->format('M d, Y'),
            'blood_type'    => $student->blood_type,
            'qr_svg'        => $qrSvg,
        ];
    }

    /**
     * Shape the school into the flat card payload.
     */
    private function cardSchool(School $school): array
    {
        return [
            'name'          => $school->name,
            'code'          => $school->code,
            'academic_year' => $school->academic_year,
            'accent_color'  => $school->accent_color,
            'logo_url'      => $school->logo_url,
            'phone'         => $school->phone,
        ];
    }

    /**
     * Guarantee the student carries a valid HMAC token for the current academic
     * year before it is encoded into the QR. A freshly-added student (or one
     * whose token predates an academic-year rollover) gets a fresh token here.
     */
    private function ensureToken(Student $student, School $school): void
    {
        if (empty($student->qr_token) || $student->qr_token_year !== $school->academic_year) {
            $this->qr->generate($student, $school);
            $student->refresh();
        }
    }

    /**
     * The QR payload embedded in each card: "UID|hmac_token"
     * Matches the format expected by QrTokenService::verifyPayloadForSchool().
     */
    private function qrPayload(Student $student): string
    {
        return $student->uid.'|'.($student->qr_token ?? '');
    }

    /**
     * Render a QR code as an inline SVG string using TCPDF's 2D barcode engine.
     * No external images, no CDN — fully self-contained, CSP-safe.
     */
    private function qrSvg(string $data, int $cells = 21): string
    {
        $barcode = new TCPDF2DBarcode($data, 'QRCODE,M');
        $grid = $barcode->getBarcodeArray();

        if (empty($grid) || empty($grid['bcode'])) {
            return '';
        }

        $rows = (int) $grid['num_rows'];
        $cols = (int) $grid['num_cols'];

        // Build path data — one "M x y h 1 v 1 h -1 Z" rect per dark cell.
        $path = '';
        foreach ($grid['bcode'] as $y => $row) {
            foreach ($row as $x => $cell) {
                if ($cell) {
                    $path .= "M{$x},{$y}h1v1h-1Z";
                }
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" '
            .'shape-rendering="crispEdges" style="width:100%%;height:100%%">'
            .'<rect width="%d" height="%d" fill="white"/>'
            .'<path d="%s" fill="black"/>'
            .'</svg>',
            $cols, $rows, $cols, $rows, $path
        );
    }
}
