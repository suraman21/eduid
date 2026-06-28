<?php

namespace App\Services;

use App\Models\CardTemplate;
use App\Models\CardTemplateField;
use Illuminate\Support\Facades\Storage;

/**
 * Converts a legacy (non-CR80) card template to the locked CR80 canvas while
 * preserving as much of the layout as possible:
 *   - the background image(s) are centre-cropped to the CR80 aspect and resized
 *     to the fixed CR80 canvas resolution, then re-stored (baked);
 *   - every field's mm coordinates are rescaled proportionally to the new canvas
 *     so its relative position is kept.
 *
 * After conversion the template prints at CR80 with no stretching — and the
 * print engine's native→CR80 scale becomes uniform (1:1 aspect), so it can never
 * distort the design.
 */
class CardTemplateNormalizer
{
    /** Convert a template in place. Returns the chosen orientation. */
    public function convert(CardTemplate $template): string
    {
        $oldW = $template->canvasW();
        $oldH = $template->canvasH();
        $orientation = $oldH > $oldW ? 'portrait' : 'landscape';
        [$newW, $newH] = CardTemplate::cr80Canvas($orientation);

        // 1. Re-bake the front/back backgrounds to the CR80 canvas (centre-crop).
        $this->cropImage($template->school_id, 'front', $newW, $newH);
        if ($template->back_image_path) {
            $this->cropImage($template->school_id, 'back', $newW, $newH);
        }

        // 2. Rescale field coordinates proportionally to the new canvas.
        $sx = $oldW > 0 ? $newW / $oldW : 1.0;
        $sy = $oldH > 0 ? $newH / $oldH : 1.0;

        foreach (CardTemplateField::where('template_id', $template->id)->get() as $field) {
            $field->update([
                'x_mm'      => round($field->x_mm * $sx, 3),
                'y_mm'      => round($field->y_mm * $sy, 3),
                'width_mm'  => $field->width_mm  !== null ? round($field->width_mm * $sx, 3) : null,
                'height_mm' => $field->height_mm !== null ? round($field->height_mm * $sy, 3) : null,
            ]);
        }

        // 3. Lock the template to the CR80 canvas.
        $template->update([
            'canvas_w'    => $newW,
            'canvas_h'    => $newH,
            'orientation' => $orientation,
        ]);

        return $orientation;
    }

    /**
     * Centre-crop the stored image for a side to the CR80 aspect and resize to
     * the canvas resolution, re-storing on both the private and public disks.
     */
    private function cropImage(int $schoolId, string $side, int $newW, int $newH): void
    {
        $rel = "cards/{$schoolId}/{$side}.png";
        if (! Storage::disk('local')->exists($rel)) {
            return;
        }

        $src = @imagecreatefrompng(Storage::disk('local')->path($rel));
        if (! $src) {
            return;
        }

        $sw = imagesx($src);
        $sh = imagesy($src);
        $targetRatio = $newW / $newH;
        $srcRatio = $sw / $sh;

        // Pick the largest centred rectangle of the source matching the target ratio.
        if ($srcRatio > $targetRatio) {
            $cropH = $sh;
            $cropW = (int) round($sh * $targetRatio);
            $cropX = (int) round(($sw - $cropW) / 2);
            $cropY = 0;
        } else {
            $cropW = $sw;
            $cropH = (int) round($sw / $targetRatio);
            $cropX = 0;
            $cropY = (int) round(($sh - $cropH) / 2);
        }

        $dst = imagecreatetruecolor($newW, $newH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $newW, $newH, $cropW, $cropH);
        imagedestroy($src);

        ob_start();
        imagepng($dst, null, 6);
        $png = ob_get_clean();
        imagedestroy($dst);

        Storage::disk('local')->put($rel, $png);
        Storage::disk('public')->put("card_previews/{$schoolId}/{$side}.png", $png);
    }
}
