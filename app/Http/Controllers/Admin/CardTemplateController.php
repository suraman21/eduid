<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CardOrder;
use App\Models\CardTemplate;
use App\Models\CardTemplateField;
use App\Models\School;
use App\Services\CardImageService;
use App\Services\CardPrintService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Super-Admin management of per-school ID card templates.
 *
 * Flow (PRD §ID-Card-Template-Spec):
 *   1. Admin uploads a designer-made background PNG/JPG (1012×638 px, 300 DPI)
 *   2. Admin positions data fields on the canvas (pixel coordinates)
 *   3. System saves fields as mm values (px / 300 * 25.4)
 *   4. On card order confirmation the template drives TCPDF generation
 */
class CardTemplateController extends Controller
{
    /** All defined field keys and their display labels. */
    public const FIELD_LABELS = [
        'full_name'     => 'Full Name (English)',
        'full_name_am'  => 'Full Name (Amharic)',
        'photo'         => 'Student Photo',
        'qr_code'       => 'QR Code',
        'uid'           => 'Student UID',
        'grade_class'   => 'Grade / Class',
        'section'       => 'Section',
        'academic_year' => 'Academic Year',
        'school_name'   => 'School Name',
        'custom_1'      => 'Custom Text 1',
        'custom_2'      => 'Custom Text 2',
        'custom_3'      => 'Custom Text 3',
    ];

    /** Show the template editor for a school. */
    public function edit(School $school)
    {
        $template = CardTemplate::where('school_id', $school->id)
            ->where('template_type', 'student')
            ->with('fields')
            ->first();

        $savedFields = $template
            ? $template->fields->map(fn($f) => [
                'field_key'    => $f->field_key,
                'x'            => $f->px_x,
                'y'            => $f->px_y,
                'w'            => $f->px_w ?? 0,
                'h'            => $f->px_h ?? 0,
                'font_family'  => $f->font_family,
                'font_size'    => $f->font_size,
                'font_color'   => $f->font_color,
                'text_align'   => $f->text_align,
                'font_weight'  => $f->font_weight,
                'custom_value' => $f->custom_value,
            ])->values()->all()
            : [];

        return view('admin.card_templates.edit', [
            'school'      => $school,
            'template'    => $template,
            'fieldLabels' => self::FIELD_LABELS,
            'savedFields' => $savedFields,
        ]);
    }

    /**
     * Upload and re-process the front or back card background image.
     * Called via fetch() from the template editor — returns JSON.
     */
    public function uploadImage(Request $request, School $school)
    {
        $request->validate([
            'side'  => ['required', 'in:front,back'],
            'image' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:5120'],
        ]);

        $side = $request->input('side');
        $file = $request->file('image');

        // Re-encode via GD: strips EXIF and normalises to PNG at CR80 size.
        $src = match ($file->getMimeType()) {
            'image/jpeg' => imagecreatefromjpeg($file->getRealPath()),
            default      => imagecreatefrompng($file->getRealPath()),
        };

        $canvas = imagecreatetruecolor(CardTemplate::CANVAS_W, CardTemplate::CANVAS_H);
        imagecopyresampled($canvas, $src, 0, 0, 0, 0,
            CardTemplate::CANVAS_W, CardTemplate::CANVAS_H,
            imagesx($src), imagesy($src)
        );
        imagedestroy($src);

        ob_start();
        imagepng($canvas, null, 6);
        $pngData = ob_get_clean();
        imagedestroy($canvas);

        $path = "cards/{$school->id}/{$side}.png";
        Storage::disk('local')->put($path, $pngData);

        AuditLog::createRecord($school->id, auth()->id(), 'card_template.image_uploaded', 'card_templates', null, null, ['side' => $side]);

        return response()->json([
            'success' => true,
            'url'     => route('admin.card-template.image', [$school, $side]),
        ]);
    }

    /**
     * Serve the front or back template image (private disk, auth-gated).
     */
    public function serveImage(School $school, string $side)
    {
        abort_unless(in_array($side, ['front', 'back'], true), 404);

        $path = "cards/{$school->id}/{$side}.png";
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=300',
            'Content-Type'  => 'image/png',
        ]);
    }

    /**
     * Save all field positions for the school's student template.
     * Accepts a JSON-encoded `fields` array in the request body.
     */
    public function saveFields(Request $request, School $school)
    {
        $request->validate([
            'fields'              => ['required', 'json'],
            'name'                => ['nullable', 'string', 'max:100'],
        ]);

        $fields = json_decode($request->input('fields'), true);

        $template = CardTemplate::updateOrCreate(
            ['school_id' => $school->id, 'template_type' => 'student'],
            [
                'name'            => $request->input('name') ?: ($school->name . ' Student Card'),
                'front_image_path' => "cards/{$school->id}/front.png",
                'back_image_path'  => Storage::disk('local')->exists("cards/{$school->id}/back.png")
                    ? "cards/{$school->id}/back.png" : null,
                'is_active'       => true,
                'created_by'      => auth()->id(),
                'version'         => 1,
            ]
        );

        // Bump version on subsequent saves (updateOrCreate sets 1 on create; increment on update).
        if (! $template->wasRecentlyCreated) {
            $template->increment('version');
        }

        // Replace all fields.
        CardTemplateField::where('template_id', $template->id)->delete();

        foreach ($fields as $i => $f) {
            $key      = $f['field_key'] ?? '';
            $fieldType = match ($key) {
                'photo'   => 'image',
                'qr_code' => 'qr',
                default   => 'text',
            };

            CardTemplateField::create([
                'template_id'  => $template->id,
                'field_type'   => $fieldType,
                'field_key'    => $key,
                'label'        => self::FIELD_LABELS[$key] ?? $key,
                'x_mm'         => $this->toMm($f['x'] ?? 0),
                'y_mm'         => $this->toMm($f['y'] ?? 0),
                'width_mm'     => isset($f['w']) && $f['w'] ? $this->toMm($f['w']) : null,
                'height_mm'    => isset($f['h']) && $f['h'] ? $this->toMm($f['h']) : null,
                'font_family'  => $f['font_family'] ?? 'Inter',
                'font_size'    => (int) ($f['font_size'] ?? 12),
                'font_color'   => $f['font_color'] ?? '#000000',
                'text_align'   => $f['text_align'] ?? 'left',
                'font_weight'  => $f['font_weight'] ?? 'normal',
                'z_index'      => $i,
                'custom_value' => $f['custom_value'] ?? null,
            ]);
        }

        AuditLog::createRecord($school->id, auth()->id(), 'card_template.saved', 'card_templates', $template->id, null, ['fields' => count($fields)]);

        return redirect()->route('admin.card-template.edit', $school)
            ->with('success', 'Template saved — ' . count($fields) . ' field(s) configured.');
    }

    /**
     * Generate cards for a confirmed order in PDF or high-quality PNG ZIP format.
     */
    public function generateCards(Request $request, CardOrder $cardOrder, CardPrintService $pdf, CardImageService $img)
    {
        $request->validate(['format' => ['required', 'in:pdf,png']]);

        $school   = $cardOrder->school;
        $template = CardTemplate::where('school_id', $school->id)
            ->where('template_type', 'student')
            ->where('is_active', true)
            ->with('fields')
            ->first();

        if ($request->input('format') === 'png') {
            if (! $template) {
                return back()->with('error', 'No active card template found. Configure a template first.');
            }
            $path = $img->generateZip($cardOrder, $template);
            AuditLog::createRecord($school->id, auth()->id(), 'card_order.png_generated', 'card_orders', $cardOrder->id);
            return Storage::disk('local')->download($path, "{$cardOrder->order_code}-cards-hq.zip");
        }

        // PDF via existing service (template-aware if template exists).
        $path = $pdf->generate($cardOrder);
        $cardOrder->update(['print_file_path' => $path]);
        AuditLog::createRecord($school->id, auth()->id(), 'card_order.pdf_generated', 'card_orders', $cardOrder->id);
        return Storage::disk('local')->download($path, "{$cardOrder->order_code}-cards.pdf");
    }

    /** px / 300 * 25.4 — store coordinates as mm. */
    private function toMm(float $px): float
    {
        return round($px / 300 * 25.4, 3);
    }
}
