<?php

namespace App\Services;

use App\Models\CardOrder;
use App\Models\CardTemplate;
use App\Models\CardTemplateField;
use App\Models\School;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use TCPDF2DBarcode;
use ZipArchive;

/**
 * High-quality PNG card generation using PHP GD + FreeType.
 *
 * Outputs one PNG per student at the template's actual canvas dimensions
 * (stored as canvas_w × canvas_h). Supports any size and orientation.
 *
 * This is Super-Admin-only. Schools never receive these files.
 */
class CardImageService
{
    /** Fallback when template has no stored canvas dimensions. */
    private const DEFAULT_W = 1012;
    private const DEFAULT_H = 638;

    public function __construct(private QrTokenService $qr)
    {
    }

    /**
     * Generate a ZIP archive of one PNG per student and return the private-disk
     * path. Callers stream it as a download; it is never web-accessible.
     */
    public function generateZip(CardOrder $order, CardTemplate $template): string
    {
        $school   = $order->school;
        $students = Student::whereIn('id', $order->student_ids ?? [])
            ->with('schoolClass')
            ->orderBy('first_name')
            ->get();

        $tmpDir = sys_get_temp_dir() . '/eduid-cards-' . $order->order_code . '-' . time();
        mkdir($tmpDir, 0755, true);

        $zipPath = "{$tmpDir}/cards.zip";
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($students as $student) {
            $this->ensureToken($student, $school);
            $pngPath = $this->renderPng($student, $school, $template, $tmpDir);
            if ($pngPath) {
                $zip->addFile($pngPath, basename($pngPath));
            }
        }

        $zip->close();

        // Move to private storage (overwrite any previous run for this order).
        $storageDir  = "print_jobs/{$order->order_code}";
        $storagePath = "{$storageDir}/cards-png-" . now()->format('Ymd-His') . '.zip';
        Storage::disk('local')->put($storagePath, file_get_contents($zipPath));

        // Clean up temp directory.
        array_map('unlink', glob("{$tmpDir}/*"));
        rmdir($tmpDir);

        return $storagePath;
    }

    /**
     * Render one student card PNG at the template's canvas dimensions, placed in $tmpDir.
     * Returns the file path on success, null on failure.
     */
    private function renderPng(Student $student, School $school, CardTemplate $template, string $tmpDir): ?string
    {
        $canvas = $this->loadBackground($template);
        if (! $canvas) {
            return null;
        }

        // Render back-side fields onto back canvas (if back image exists).
        $backCanvas = $this->loadBackBackground($template);

        $template->fields->each(function (CardTemplateField $field) use ($canvas, $backCanvas, $student, $school) {
            $target = ($field->side ?? 'front') === 'back' && $backCanvas ? $backCanvas : $canvas;
            match ($field->field_type) {
                'text'  => $this->drawText($target, $field, $student, $school),
                'image' => $this->drawPhoto($target, $field, $student),
                'qr'    => $this->drawQr($target, $field, $student),
                default => null,
            };
        });

        $filename = sanitize_filename($student->uid) . '.png';
        $outPath  = "{$tmpDir}/{$filename}";
        imagepng($canvas, $outPath, 9);
        imagedestroy($canvas);

        // If a back canvas was rendered, save it as a separate file (uid-back.png).
        if ($backCanvas) {
            $backPath = "{$tmpDir}/" . sanitize_filename($student->uid) . '-back.png';
            imagepng($backCanvas, $backPath, 9);
            imagedestroy($backCanvas);
        }

        return $outPath;
    }

    /** Load the back background image, or null if no back image is configured. */
    private function loadBackBackground(CardTemplate $template): mixed
    {
        if (! $template->back_image_path) {
            return null;
        }
        $path = $this->diskPath($template->back_image_path);
        if (! $path) {
            return null;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $src = match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            default        => @imagecreatefrompng($path),
        };
        return $src ?: null;
    }

    /** Load the front background image as a GD resource at the template's canvas size. */
    private function loadBackground(CardTemplate $template): mixed
    {
        $path = $this->diskPath($template->front_image_path);
        if (! $path) {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $src = match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            default        => @imagecreatefrompng($path),
        };
        if (! $src) {
            return null;
        }

        $targetW = $template->canvasW();
        $targetH = $template->canvasH();

        // If stored dimensions already match, use as-is.
        if (imagesx($src) === $targetW && imagesy($src) === $targetH) {
            return $src;
        }

        // Otherwise scale to the stored canvas size (in case the file was re-uploaded at different dims).
        $canvas = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $src, 0, 0, 0, 0, $targetW, $targetH, imagesx($src), imagesy($src));
        imagedestroy($src);

        return $canvas;
    }

    /** Render a text field using FreeType / TTF. */
    private function drawText(mixed $canvas, CardTemplateField $field, Student $student, School $school): void
    {
        $text = $this->fieldValue($field->field_key, $student, $school, $field->custom_value);
        if ($text === '') {
            return;
        }

        $font     = $this->fontPath($field->font_family ?? 'Inter');
        $fontSize = (int) ($field->font_size ?? 12);
        $color    = $this->gdColor($canvas, $field->font_color ?? '#000000');

        $x  = $field->px_x;
        $y  = $field->px_y + $fontSize; // GD baseline

        // Horizontal alignment: measure text width and shift X.
        if (in_array($field->text_align, ['center', 'right'], true) && $field->width_mm) {
            $box   = imagettfbbox($fontSize, 0, $font, $text);
            $tw    = abs($box[4] - $box[0]);
            $fw    = $field->px_w ?? 0;
            $x = match ($field->text_align) {
                'center' => $x + (int) (($fw - $tw) / 2),
                'right'  => $x + $fw - $tw,
                default  => $x,
            };
        }

        imagettftext($canvas, $fontSize, 0, $x, $y, $color, $font, $text);
    }

    /** Composite the student's photo into the field rectangle. */
    private function drawPhoto(mixed $canvas, CardTemplateField $field, Student $student): void
    {
        $photoPath = null;
        if ($student->photo_path) {
            foreach (['local', 'public'] as $disk) {
                if (Storage::disk($disk)->exists($student->photo_path)) {
                    $photoPath = Storage::disk($disk)->path($student->photo_path);
                    break;
                }
            }
        }

        if (! $photoPath) {
            return; // Leave the template's placeholder area as-is.
        }

        $ext   = strtolower(pathinfo($photoPath, PATHINFO_EXTENSION));
        $photo = match ($ext) {
            'jpg', 'jpeg' => imagecreatefromjpeg($photoPath),
            default        => imagecreatefrompng($photoPath),
        };
        if (! $photo) {
            return;
        }

        $pw = $field->px_w ?? 80;
        $ph = $field->px_h ?? 100;
        $r  = (int) ($field->border_radius ?? 0);

        // Scale photo to destination size on a temp canvas with alpha.
        $tmp = imagecreatetruecolor($pw, $ph);
        imagealphablending($tmp, false);
        imagesavealpha($tmp, true);
        $trans = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
        imagefill($tmp, 0, 0, $trans);
        imagecopyresampled($tmp, $photo, 0, 0, 0, 0, $pw, $ph, imagesx($photo), imagesy($photo));
        imagedestroy($photo);

        if ($r > 0) {
            // Apply rounded-corner mask: walk the four corner quadrants and punch out
            // pixels that fall outside the arc radius. O(r²) — fast for typical sizes.
            $r = min($r, (int)($pw / 2), (int)($ph / 2));
            for ($cy = 0; $cy < $r; $cy++) {
                for ($cx = 0; $cx < $r; $cx++) {
                    $dist = sqrt(($r - $cx) ** 2 + ($r - $cy) ** 2);
                    if ($dist > $r) {
                        $clear = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
                        imagesetpixel($tmp, $cx,          $cy,          $clear); // top-left
                        imagesetpixel($tmp, $pw - 1 - $cx, $cy,          $clear); // top-right
                        imagesetpixel($tmp, $cx,          $ph - 1 - $cy, $clear); // bottom-left
                        imagesetpixel($tmp, $pw - 1 - $cx, $ph - 1 - $cy, $clear); // bottom-right
                    }
                }
            }
        }

        imagealphablending($canvas, true);
        imagecopy($canvas, $tmp, $field->px_x, $field->px_y, 0, 0, $pw, $ph);
        imagedestroy($tmp);
    }

    /** Render the student's HMAC QR code directly into the GD canvas. */
    private function drawQr(mixed $canvas, CardTemplateField $field, Student $student): void
    {
        $token = $student->qr_token ?? '';
        if ($token === '') {
            return;
        }

        $barcode = new TCPDF2DBarcode($token, 'QRCODE,M');
        $grid    = $barcode->getBarcodeArray();
        if (empty($grid['bcode'])) {
            return;
        }

        $rows   = (int) $grid['num_rows'];
        $cols   = (int) $grid['num_cols'];
        $qw     = $field->px_w ?? 100;
        $qh     = $field->px_h ?? 100;
        $cellW  = $qw / $cols;
        $cellH  = $qh / $rows;

        $black = imagecolorallocate($canvas, 0, 0, 0);
        $white = imagecolorallocate($canvas, 255, 255, 255);

        // Fill background.
        imagefilledrectangle($canvas, $field->px_x, $field->px_y, $field->px_x + $qw - 1, $field->px_y + $qh - 1, $white);

        foreach ($grid['bcode'] as $row => $cols_data) {
            foreach ($cols_data as $col => $cell) {
                if ($cell) {
                    $x1 = $field->px_x + (int) round($col * $cellW);
                    $y1 = $field->px_y + (int) round($row * $cellH);
                    $x2 = $field->px_x + (int) round(($col + 1) * $cellW) - 1;
                    $y2 = $field->px_y + (int) round(($row + 1) * $cellH) - 1;
                    imagefilledrectangle($canvas, $x1, $y1, $x2, $y2, $black);
                }
            }
        }
    }

    // ── Helpers ────────────────────────────────────────────────

    private function fieldValue(string $key, Student $student, School $school, ?string $customValue): string
    {
        return match ($key) {
            'full_name'     => $student->full_name,
            'full_name_am'  => $student->full_name_am ?? '',
            'uid'           => $student->uid,
            'grade_class'   => $student->schoolClass?->display_name ?? '',
            'section'       => $student->schoolClass?->section ?? '',
            'academic_year' => $school->academic_year,
            'school_name'   => $school->name,
            'custom_1', 'custom_2', 'custom_3' => (string) ($customValue ?? ''),
            default         => '',
        };
    }

    private function fontPath(string $family): string
    {
        $map = [
            'NotoSansEthiopic' => base_path('resources/fonts/NotoSansEthiopic.ttf'),
            'Outfit'           => base_path('resources/fonts/Inter.ttf'),
            'Inter'            => base_path('resources/fonts/Inter.ttf'),
            'Roboto'           => base_path('resources/fonts/Inter.ttf'),
        ];
        $path = $map[$family] ?? '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf';
        return file_exists($path) ? $path : base_path('resources/fonts/Inter.ttf');
    }

    private function gdColor(mixed $canvas, string $hex): int
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return imagecolorallocate(
            $canvas,
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2))
        );
    }

    private function diskPath(string $storagePath): ?string
    {
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($storagePath)) {
                return Storage::disk($disk)->path($storagePath);
            }
        }
        return null;
    }

    private function ensureToken(Student $student, School $school): void
    {
        if (empty($student->qr_token) || $student->qr_token_year !== $school->academic_year) {
            $this->qr->generate($student, $school);
            $student->refresh();
        }
    }
}

// Global helper — sanitize a UID for use as a filename.
if (! function_exists('sanitize_filename')) {
    function sanitize_filename(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '-', $name);
    }
}
