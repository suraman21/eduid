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
use App\Services\CardTemplateNormalizer;
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
        'date_of_birth' => 'Date of Birth',
        'blood_type'    => 'Blood Type',
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
                'side'         => $f->side ?? 'front',
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
                'border_radius'=> $f->border_radius ?? 0,
            ])->values()->all()
            : [];

        $canvasW = $template?->canvasW() ?? CardTemplate::DEFAULT_W;
        $canvasH = $template?->canvasH() ?? CardTemplate::DEFAULT_H;

        $frontPublicUrl = $this->bustedTemplateUrl($school, 'front');

        $backExists = $template && Storage::disk('local')->exists("cards/{$school->id}/back.png");
        $backPublicUrl = $this->bustedTemplateUrl($school, 'back');

        $backInfo = $backExists ? @getimagesize(Storage::disk('local')->path("cards/{$school->id}/back.png")) : null;
        $backW    = $backInfo ? $backInfo[0] : $canvasW;
        $backH    = $backInfo ? $backInfo[1] : $canvasH;

        return \Inertia\Inertia::render('Admin/CardTemplates/Edit', [
            'school'      => ['id' => $school->id, 'name' => $school->name],
            'template'    => $template ? ['version' => $template->version, 'field_count' => $template->fields->count()] : null,
            'fieldLabels' => self::FIELD_LABELS,
            'savedFields' => $savedFields,
            'canvasW'     => $canvasW,
            'canvasH'     => $canvasH,
            'backW'       => $backW,
            'backH'       => $backH,
            'frontUrl'    => $frontPublicUrl,
            'backUrl'     => $backPublicUrl,
            'uploadUrl'   => route('admin.card-template.upload', $school),
            'saveUrl'     => route('admin.card-template.save', $school),
            'convertUrl'  => route('admin.card-template.convert', $school),
            'backUrl_exists' => $backExists,
            'schoolUrl'   => route('admin.schools.show', $school),
            // Fixed CR80 design canvas — the editor locks to one of these.
            'orientation' => $template?->orientation ?? ($canvasH > $canvasW ? 'portrait' : 'landscape'),
            'isLegacy'    => $template ? $template->isLegacy() : false,
            'cr80'        => [
                'landscape' => ['w' => CardTemplate::CANVAS_LANDSCAPE_W, 'h' => CardTemplate::CANVAS_LANDSCAPE_H],
                'portrait'  => ['w' => CardTemplate::CANVAS_PORTRAIT_W,  'h' => CardTemplate::CANVAS_PORTRAIT_H],
            ],
        ]);
    }

    /**
     * Resolve a cache-busted URL for a side's background. The ?v=<mtime> changes
     * whenever the artwork is replaced, so the editor never shows a stale cached
     * image on revisit (the URL is otherwise static).
     */
    private function bustedTemplateUrl(School $school, string $side): ?string
    {
        $public = "card_previews/{$school->id}/{$side}.png";
        if (Storage::disk('public')->exists($public)) {
            return Storage::disk('public')->url($public).'?v='.Storage::disk('public')->lastModified($public);
        }
        $local = "cards/{$school->id}/{$side}.png";
        if (Storage::disk('local')->exists($local)) {
            return route('admin.card-template.image', [$school, $side]).'?v='.Storage::disk('local')->lastModified($local);
        }
        return null;
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

        // The editor bakes the designer's crop to the CR80 aspect before upload.
        // Reject anything that is not CR80 so the canvas ratio can never drift.
        if (! CardTemplate::isRatioCr80((int) $origW, (int) $origH)) {
            return response()->json([
                'success' => false,
                'error'   => 'Background must be cropped to the CR80 aspect ratio (85.6 × 54 mm). Please re-crop in the editor.',
            ], 422);
        }

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
                    ->update([
                        'canvas_w'    => $origW,
                        'canvas_h'    => $origH,
                        'orientation' => $origH > $origW ? 'portrait' : 'landscape',
                    ]);
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

        // CR80 is mandatory — the canvas aspect can never drift. The editor only
        // ever submits a CR80 canvas; this is the server-side guarantee.
        if (! CardTemplate::isRatioCr80($canvasW, $canvasH)) {
            $msg = 'The card canvas must use the CR80 aspect ratio (85.6 × 54 mm). Re-crop the background in the editor.';

            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->with('error', $msg);
        }

        $template = CardTemplate::updateOrCreate(
            ['school_id' => $school->id, 'template_type' => 'student'],
            [
                'name'             => $request->input('name') ?: ($school->name . ' Student Card'),
                'front_image_path' => "cards/{$school->id}/front.png",
                'back_image_path'  => Storage::disk('local')->exists("cards/{$school->id}/back.png")
                    ? "cards/{$school->id}/back.png" : null,
                'canvas_w'         => $canvasW,
                'canvas_h'         => $canvasH,
                'orientation'      => $canvasH > $canvasW ? 'portrait' : 'landscape',
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
     * Auto-convert a legacy (non-CR80) template to the locked CR80 canvas:
     * centre-crop the background and proportionally rescale field positions, then
     * return to the editor for manual touch-up. Idempotent for CR80 templates.
     */
    public function convertLegacy(School $school, CardTemplateNormalizer $normalizer)
    {
        $template = CardTemplate::where('school_id', $school->id)
            ->where('template_type', 'student')
            ->first();

        if (! $template) {
            return back()->with('error', 'No template to convert.');
        }

        if (! $template->isLegacy()) {
            return back()->with('success', 'Template already uses the CR80 aspect ratio.');
        }

        $orientation = $normalizer->convert($template);

        AuditLog::createRecord(
            $school->id, auth()->id(),
            'card_template.converted_cr80', 'card_templates', $template->id, null,
            ['orientation' => $orientation]
        );

        return redirect()->route('admin.card-template.edit', $school)
            ->with('success', "Template converted to CR80 ({$orientation}). Fine-tune the crop and field positions, then save.");
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
