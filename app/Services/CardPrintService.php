<?php

namespace App\Services;

use App\Models\CardOrder;
use App\Models\School;
use App\Models\Student;
use Illuminate\Support\Facades\Storage;
use TCPDF;

/**
 * Server-side, Super-Admin-only generation of print-ready ID cards (PRD §03).
 *
 * The Golden Rule: schools NEVER receive a print-ready file — they only see a
 * low-res, watermarked on-screen preview. This service produces the real
 * artwork: one CR80 (85.6 × 54 mm) card per page at full quality, arranged for
 * the PVC card printer, written to a private path under storage/app that is
 * never exposed via a web URL.
 */
class CardPrintService
{
    /** CR80 card dimensions in millimetres (ISO/IEC 7810 ID-1). */
    private const CARD_W = 85.6;
    private const CARD_H = 54.0;

    public function __construct(private QrTokenService $qr)
    {
    }

    /**
     * Build the print-ready PDF for an order and return its storage-relative
     * path (under the 'local' private disk). Each card is one page sized to the
     * exact card so the printer drops them straight onto blank PVC stock.
     */
    public function generate(CardOrder $order): string
    {
        $school   = $order->school;
        $students = Student::whereIn('id', $order->student_ids ?? [])
            ->with('schoolClass')
            ->orderBy('first_name')
            ->get();

        $pdf = new TCPDF('L', 'mm', [self::CARD_W, self::CARD_H], true, 'UTF-8', false);
        $pdf->SetCreator('EduID');
        $pdf->SetTitle("ID Cards — {$order->order_code}");
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        foreach ($students as $student) {
            $this->ensureToken($student, $school);
            $pdf->AddPage();
            $this->drawCard($pdf, $student, $school);
        }

        // Private, non-web path. Old files for this order are replaced.
        $dir  = "print_jobs/{$order->order_code}";
        Storage::disk('local')->deleteDirectory($dir);
        $path = "{$dir}/cards-" . now()->format('Ymd-His') . '.pdf';

        Storage::disk('local')->put($path, $pdf->Output('cards.pdf', 'S'));

        return $path;
    }

    /**
     * Make sure the student carries a valid HMAC token for the current academic
     * year before it is rendered into the QR code. Promotion normally mints
     * these; this is a safety net so a freshly-added student still prints a
     * scannable card.
     */
    private function ensureToken(Student $student, School $school): void
    {
        if (empty($student->qr_token) || $student->qr_token_year !== $school->academic_year) {
            $this->qr->generate($student, $school);
            $student->refresh();
        }
    }

    /**
     * Render a single card onto the current page.
     */
    private function drawCard(TCPDF $pdf, Student $student, School $school): void
    {
        $accent = $this->rgb($school->accent_color ?? '#6366f1');

        // ── Header bar (accent) ──
        $pdf->SetFillColor(...$accent);
        $pdf->Rect(0, 0, self::CARD_W, 11, 'F');

        $logoPath = $this->logoPath($school);
        $textX    = 3.5;
        if ($logoPath) {
            $pdf->Image($logoPath, 2.5, 2, 7, 7, '', '', '', true, 300, '', false, false, 0);
            $textX = 11;
        }

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetXY($textX, 2);
        $pdf->Cell(50, 3.2, $this->clip($school->name, 34), 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 5);
        $pdf->SetXY($textX, 5.4);
        $pdf->Cell(50, 2.6, $school->academic_year, 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 5);
        $pdf->SetXY(self::CARD_W - 24, 3.6);
        $pdf->Cell(21, 3, 'STUDENT ID', 0, 0, 'R');

        // ── Photo ──
        $photoPath = $this->photoPath($student);
        $px = 3.5; $py = 14; $pw = 17; $ph = 21;
        if ($photoPath) {
            $pdf->Image($photoPath, $px, $py, $pw, $ph, '', '', '', true, 300, '', false, false, 1, 'CM');
        } else {
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->Rect($px, $py, $pw, $ph, 'DF');
            $pdf->SetTextColor(150, 150, 150);
            $pdf->SetFont('helvetica', '', 4);
            $pdf->SetXY($px, $py + $ph / 2 - 1.5);
            $pdf->Cell($pw, 3, 'No Photo', 0, 0, 'C');
        }

        // ── Info block ──
        $ix = $px + $pw + 3;
        $pdf->SetTextColor(17, 24, 39);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($ix, 14.5);
        $pdf->Cell(38, 3.6, $this->clip($student->full_name, 26), 0, 0, 'L');

        $y = 18.4;
        if ($student->full_name_am) {
            $pdf->SetTextColor(107, 114, 128);
            $pdf->SetFont('helvetica', '', 6);
            $pdf->SetXY($ix, $y);
            $pdf->Cell(38, 3, $this->clip($student->full_name_am, 30), 0, 0, 'L');
            $y += 3.2;
        }

        $rows = [
            'Class' => $student->schoolClass?->display_name ?? '—',
            'DOB'   => $student->date_of_birth?->format('M d, Y') ?? '—',
        ];
        if ($student->blood_type) {
            $rows['Blood'] = $student->blood_type;
        }

        $pdf->SetFont('helvetica', '', 6);
        foreach ($rows as $label => $value) {
            $pdf->SetTextColor(156, 163, 175);
            $pdf->SetXY($ix, $y);
            $pdf->Cell(8, 3, $label, 0, 0, 'L');
            $pdf->SetTextColor(55, 65, 81);
            $pdf->SetXY($ix + 8, $y);
            $pdf->Cell(30, 3, $this->clip((string) $value, 24), 0, 0, 'L');
            $y += 3.1;
        }

        // ── QR code (bottom-right) ──
        $qrData  = "{$student->uid}|{$student->qr_token}";
        $qrStyle = [
            'border' => false, 'vpadding' => 0, 'hpadding' => 0,
            'fgcolor' => [0, 0, 0], 'bgcolor' => [255, 255, 255],
            'module_width' => 1, 'module_height' => 1,
        ];
        $qs = 15;
        $pdf->write2DBarcode($qrData, 'QRCODE,M', self::CARD_W - $qs - 3.5, 16, $qs, $qs, $qrStyle, 'N');
        $pdf->SetTextColor(120, 120, 120);
        $pdf->SetFont('courier', '', 4.2);
        $pdf->SetXY(self::CARD_W - $qs - 3.5, 31.5);
        $pdf->Cell($qs, 2.4, $student->uid, 0, 0, 'C');

        // ── Footer bar ──
        $pdf->SetFillColor(249, 250, 251);
        $pdf->Rect(0, self::CARD_H - 5, self::CARD_W, 5, 'F');
        $pdf->SetTextColor(156, 163, 175);
        $pdf->SetFont('helvetica', '', 5);
        $pdf->SetXY(3, self::CARD_H - 4);
        $pdf->Cell(40, 3, $school->code, 0, 0, 'L');
        $pdf->SetXY(self::CARD_W - 43, self::CARD_H - 4);
        $pdf->Cell(40, 3, (string) ($school->phone ?? ''), 0, 0, 'R');
    }

    // ── helpers ────────────────────────────────────────────────

    /** Absolute filesystem path to a student's photo, or null. */
    private function photoPath(Student $student): ?string
    {
        if (! $student->photo_path) {
            return null;
        }
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($student->photo_path)) {
                return Storage::disk($disk)->path($student->photo_path);
            }
        }
        return null;
    }

    /** Absolute filesystem path to the school logo, or null. */
    private function logoPath(School $school): ?string
    {
        if (! $school->logo_path) {
            return null;
        }
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($school->logo_path)) {
                return Storage::disk($disk)->path($school->logo_path);
            }
        }
        return null;
    }

    /** Hex colour → [r,g,b]. */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /** Truncate long strings so they never overflow the fixed card layout. */
    private function clip(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max - 1) . '…' : $value;
    }
}
