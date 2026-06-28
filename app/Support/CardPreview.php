<?php

namespace App\Support;

use App\Models\CardTemplate;
use App\Models\School;
use Illuminate\Support\Facades\Storage;
use TCPDF2DBarcode;

/**
 * Shapes a CardTemplate into the px-coordinate payload the shared TemplateCard
 * React renderer consumes, and builds inline QR SVGs. Extracted so the order
 * print view and the professional sheet-print wizard render identical card art.
 */
class CardPreview
{
    /**
     * Background images are served from the existing admin-gated template-image
     * route (no base64 bloat). Coordinates are canvas pixels at 300 DPI.
     */
    public static function payload(School $school, CardTemplate $template): array
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
    public static function qrSvg(string $data): string
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
}
