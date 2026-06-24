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
 *   1. Admin uploads a designer-made background PNG/JPG (any size, any orientation)
 *   2. Admin positions data fields on the canvas (pixel coordinates)
 *   3. System saves fields as mm values (px / 300 * 25.4)
 *   4. On card order confirmation the template drives TCPDF/GD generation
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
            'canvasW'     => $template?->canvasW() ?? CardTemplate::DEFAULT_W,
            'canvasH'     => $template?->canvasH() ?? CardTemplate::DEFAULT_H,
        ]);
    }

    /**
     * Upload the front or back card background image.
     * Preserves the original image dimensions — the image size defines the template canvas.
     * Returns JSON so the editor JS can update the live preview immediately.
     */
    public function uploadImage(Request $request, School $school)
    {
        $request->validate([
            'side'  => ['required', 'in:front,back'],
            'image' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:10240'],
        ]);

        $side = $request->input('side');
        $file = $request->file('image');

        // Detect actual pixel dimensions before any processing.
        $info = @getimagesize($file->getRealPath());
        if (! $info) {
            return response()->json(['success' => false, 'error' => 'Could not read image dimensions.'], 422);
        }
        [$origW, $origH] = $info;

        // Load via GD — handles JPEG and PNG; re-encodes to PNG to strip EXIF metadata.
        // We do NOT resize: the designer's image dimensions define the canvas.
        $mime = $file->getMimeType();
        $src  = match (true) {
            in_array($mime, ['image/jpeg', 'image/jpg']) => @imagecreatefromjpeg($file->getRealPath()),
            default                                       => @imagecreatefrompng($file->getRealPath()),
        };

        $pngData = null;
        if ($src) {
            // Re-encode as PNG at the original dimensions (EXIF-stripped, format-normalised).
            $canvas = imagecreatetruecolor($origW, $origH);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagecopy($canvas, $src, 0, 0, 0, 0, $origW, $origH);
            imagedestroy($src);

            ob_start();
            imagepng($canvas, null, 6);
            $pngData = ob_get_clean();
            imagedestroy($canvas);

            $stored = Storage::disk('local')->put("cards/{$school->id}/{$side}.png", $pngData);
        } else {
            // GD failed (unusual format / non-RGB) — store the raw file as-is.
            $pngData = file_get_contents($file->getRealPath());
            $stored  = Storage::disk('local')->put("cards/{$school->id}/{$side}.png", $pngData);
        }

        if (! $stored) {
            return response()->json(['success' => false, 'error' => 'Storage write failed.'], 500);
        }

        // Also write to public disk so the preview can be served as a direct static file
        // (no PHP bootstrap overhead — ~20× faster than the auth-gated controller route).
        Storage::disk('public')->put("card_previews/{$school->id}/{$side}.png", $pngData);

        // Persist canvas dimensions so CardImageService and CardPrintService can use them.
        if ($side === 'front') {
            try {
                CardTemplate::where('school_id', $school->id)
                    ->where('template_type', 'student')
                    ->update(['canvas_w' => $origW, 'canvas_h' => $origH]);
            } catch (\Throwable $e) {
                // Non-fatal — canvas_w/h columns may be pending migration.
            }
        }

        AuditLog::createRecord(
            $school->id, auth()->id(),
            'card_template.image_uploaded', 'card_templates', null, null,
            ['side' => $side, 'width' => $origW, 'height' => $origH]
        );

        return response()->json([
            'success'     => true,
            'url'         => Storage::disk('public')->url("card_previews/{$school->id}/{$side}.png"),
            'width'       => $origW,
            'height'      => $origH,
        ]);
    }

    /**
     * Serve the front or back template image from the private disk (auth-gated).
     */
    public function serveImage(School $school, string $side)
    {
        abort_unless(in_array($side, ['front', 'back'], true), 404);

        $path = "cards/{$school->id}/{$side}.png";
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=60',
            'Content-Type'  => 'image/png',
        ]);
    }

    /**
     * Save all field positions for the school's student template.
     */
    public function saveFields(Request $request, School $school)
    {
        $request->validate([
            'fields'   => ['required', 'json'],
            'name'     => ['nullable', 'string', 'max:100'],
            'canvas_w' => ['nullable', 'integer', 'min:100', 'max:10000'],
            'canvas_h' => ['nullable', 'integer', 'min:100', 'max:10000'],
        ]);

        $fields  = json_decode($request->input('fields'), true);
        $canvasW = (int) ($request->input('canvas_w') ?: CardTemplate::DEFAULT_W);
        $canvasH = (int) ($request->input('canvas_h') ?: CardTemplate::DEFAULT_H);

        $template = CardTemplate::updateOrCreate(
            ['school_id' => $school->id, 'template_type' => 'student'],
            [
                'name'             => $request->input('name') ?: ($school->name . ' Student Card'),
                'front_image_path' => "cards/{$school->id}/front.png",
                'back_image_path'  => Storage::disk('local')->exists("cards/{$school->id}/back.png")
                    ? "cards/{$school->id}/back.png" : null,
                'canvas_w'         => $canvasW,
                'canvas_h'         => $canvasH,
                'is_active'        => true,
                'created_by'       => auth()->id(),
            ]
        );

        if (! $template->wasRecentlyCreated) {
            $template->increment('version');
        }

        CardTemplateField::where('template_id', $template->id)->delete();

        foreach ($fields as $i => $f) {
            $key      = $f['field_key'] ?? '';
            $fieldType = match ($key) {
                'photo'   => 'image',
                'qr_code' => 'qr',
                default   => 'text',
            };

            CardTemplateField::create([
                'template_id'   => $template->id,
                'field_type'    => $fieldType,
                'field_key'     => $key,
                'label'         => self::FIELD_LABELS[$key] ?? $key,
                'x_mm'          => $this->toMm($f['x'] ?? 0),
                'y_mm'          => $this->toMm($f['y'] ?? 0),
                'width_mm'      => isset($f['w']) && $f['w'] ? $this->toMm($f['w']) : null,
                'height_mm'     => isset($f['h']) && $f['h'] ? $this->toMm($f['h']) : null,
                'font_family'   => $f['font_family'] ?? 'Inter',
                'font_size'     => (int) ($f['font_size'] ?? 12),
                'font_color'    => $f['font_color'] ?? '#000000',
                'text_align'    => $f['text_align'] ?? 'left',
                'font_weight'   => $f['font_weight'] ?? 'normal',
                'z_index'       => $i,
                'side'          => in_array($f['side'] ?? '', ['front', 'back']) ? $f['side'] : 'front',
                'custom_value'  => $f['custom_value'] ?? null,
                'border_radius' => (int) ($f['border_radius'] ?? 0),
            ]);
        }

        AuditLog::createRecord(
            $school->id, auth()->id(),
            'card_template.saved', 'card_templates', $template->id, null,
            ['fields' => count($fields), 'canvas' => "{$canvasW}×{$canvasH}"]
        );

        $msg = 'Template saved — ' . count($fields) . ' field(s).';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'version' => $template->fresh()->version,
            ]);
        }

        return redirect()->route('admin.card-template.edit', $school)->with('success', $msg);
    }

    /**
     * Generate cards for a confirmed order in PDF or high-quality PNG ZIP.
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
