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
 * Outputs 1012×638 px images (CR80 at 300 DPI) suitable for professional
 * PVC card printing. Each student's card is a full-colour composite:
 *   1. Designer's background image (PNG/JPG)
 *   2. Data overlay at template-configured pixel positions
 *
 * This is Super-Admin-only. Schools never receive these files.
 */
class CardImageService
{
    /** CR80 at 300 DPI. */
    private const W = 1012;
    private const H = 638;

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
     * Render one student card as a 1012×638 PNG file, placed in $tmpDir.
     * Returns the file path on success, null on failure.
     */
    private function renderPng(Student $student, School $school, CardTemplate $template, string $tmpDir): ?string
    {
        $canvas = $this->loadBackground($template);
        if (! $canvas) {
            return null;
        }

        $template->fields->each(function (CardTemplateField $field) use ($canvas, $student, $school) {
            match ($field->field_type) {
                'text'  => $this->drawText($canvas, $field, $student, $school),
                'image' => $this->drawPhoto($canvas, $field, $student),
                'qr'    => $this->drawQr($canvas, $field, $student),
                default => null,
            };
        });

        $filename = sanitize_filename($student->uid) . '.png';
        $outPath  = "{$tmpDir}/{$filename}";
        imagepng($canvas, $outPath, 9); // max compression for smallest file
        imagedestroy($canvas);

        return $outPath;
    }

    /** Load the front background image as a GD resource. */
    private function loadBackground(CardTemplate $template): mixed
    {
        $path = $this->diskPath($template->front_image_path);
        if (! $path) {
            return null;
        }

        $ext    = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $canvas = match ($ext) {
            'jpg', 'jpeg' => imagecreatefromjpeg($path),
            default        => imagecreatefrompng($path),
        };
        if (! $canvas) {
            return null;
        }

        // Ensure exact CR80 dimensions.
        if (imagesx($canvas) !== self::W || imagesy($canvas) !== self::H) {
            $resized = imagecreatetruecolor(self::W, self::H);
            imagecopyresampled($resized, $canvas, 0, 0, 0, 0, self::W, self::H, imagesx($canvas), imagesy($canvas));
            imagedestroy($canvas);
            $canvas = $resized;
        }

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
        imagecopyresampled($canvas, $photo, $field->px_x, $field->px_y, 0, 0, $pw, $ph, imagesx($photo), imagesy($photo));
        imagedestroy($photo);
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
