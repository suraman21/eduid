<?php

namespace App\Services;

use App\Models\CardTemplate;
use App\Models\School;
use App\Models\SheetTemplate;
use App\Models\Student;
use TCPDF;

/**
 * Professional multi-up sheet printing (PRD — ID Card Printing Module).
 *
 * Composes individual CR80 cards onto a physical sheet (e.g. A4 PVC 10-up) at
 * EXACT millimetre slot coordinates. There is no scaling and no browser layout:
 * each card is drawn at the sheet origin inside a TCPDF Translate transform, so
 * every field keeps the precise mm position the designer set. The same slot
 * coordinates are used for the front and back PDFs, guaranteeing front/back
 * registration (the back of slot N is always the back of the same student).
 *
 * Calibration offsets (Phase 4) are passed straight into the translate, so the
 * whole sheet shifts uniformly to match a given printer's paper feed.
 */
class SheetPrintService
{
    public function __construct(private CardPrintService $cards) {}

    /**
     * Render one side of a sheet to raw PDF bytes.
     *
     * @param  array<int, array{slot: int, student: Student}>  $assignments
     * @param  string  $side  'front' | 'back'
     * @param  array{x: float, y: float}  $offset  calibration shift in mm
     */
    public function render(
        SheetTemplate $sheet,
        ?CardTemplate $template,
        School $school,
        array $assignments,
        string $side,
        array $offset = ['x' => 0.0, 'y' => 0.0],
        float $rotationDeg = 0.0,
        bool $mirror = false,
    ): string {
        $pdf = $this->newSheetPdf($sheet);

        // Whole-page calibration transforms (printer profile): rotation about the
        // sheet centre and/or horizontal mirror for printers that need it.
        $pageTransform = ($rotationDeg != 0.0) || $mirror;
        if ($pageTransform) {
            $pdf->StartTransform();
            if ($mirror) {
                $pdf->MirrorH($sheet->sheet_w_mm / 2);
            }
            if ($rotationDeg != 0.0) {
                $pdf->Rotate($rotationDeg, $sheet->sheet_w_mm / 2, $sheet->sheet_h_mm / 2);
            }
        }

        $positions = $sheet->slotPositions();

        // The card's true physical size (CR80, oriented to the design). When the
        // card orientation differs from the slot orientation, the card is ROTATED
        // 90° to fit the slot — never squished. This lets a portrait card be
        // designed upright and laid into landscape slots without distortion.
        $cardW = $template ? $template->cardWMm() : 85.6;
        $cardH = $template ? $template->cardHMm() : 54.0;
        $cardPortrait = $cardH > $cardW;
        $slotPortrait = $sheet->card_h_mm > $sheet->card_w_mm;
        $rotate = $cardPortrait !== $slotPortrait;

        $offX = $offset['x'] ?? 0;
        $offY = $offset['y'] ?? 0;
        $sw = $sheet->card_w_mm;
        $sh = $sheet->card_h_mm;

        foreach ($assignments as $a) {
            $pos = $positions[$a['slot']] ?? null;
            if (! $pos) {
                continue;
            }

            /** @var Student $student */
            $student = $a['student'];
            $this->cards->ensureToken($student, $school);

            $pdf->StartTransform();

            if ($rotate) {
                // Rotate the card 90° about the slot centre and draw it at its own
                // CR80 size; the rotated footprint (cardH × cardW) equals the slot.
                $cx = $pos['x'] + $sw / 2 + $offX;
                $cy = $pos['y'] + $sh / 2 + $offY;
                $pdf->Rotate(90, $cx, $cy);
                $pdf->Translate($cx - $cardW / 2, $cy - $cardH / 2);
                $this->cards->drawAnyCardSide($pdf, $student, $school, $template, $side, $cardW, $cardH);
            } else {
                // Orientation matches — fill the slot at its exact physical size.
                $pdf->Translate($pos['x'] + $offX, $pos['y'] + $offY);
                $this->cards->drawAnyCardSide($pdf, $student, $school, $template, $side, $sw, $sh);
            }

            $pdf->StopTransform();
        }

        if ($pageTransform) {
            $pdf->StopTransform();
        }

        return $pdf->Output('sheet.pdf', 'S');
    }

    /**
     * Calibration test sheet (calibration wizard, print module spec).
     *
     * Draws corner crosshairs for every slot, slot numbers, mm rulers along the
     * top and left edges, and a side label. The operator prints FRONT, flips the
     * sheet per the printer's flip mode, prints BACK, then holds the sheet to
     * the light: the measured shift between front and back crosses (in mm) is
     * entered into the printer profile, which future prints compensate with.
     */
    public function renderCalibration(
        SheetTemplate $sheet,
        string $side,
        array $offset = ['x' => 0.0, 'y' => 0.0],
        float $rotationDeg = 0.0,
        bool $mirror = false,
    ): string {
        $pdf = $this->newSheetPdf($sheet);

        $pageTransform = ($rotationDeg != 0.0) || $mirror;
        if ($pageTransform) {
            $pdf->StartTransform();
            if ($mirror) {
                $pdf->MirrorH($sheet->sheet_w_mm / 2);
            }
            if ($rotationDeg != 0.0) {
                $pdf->Rotate($rotationDeg, $sheet->sheet_w_mm / 2, $sheet->sheet_h_mm / 2);
            }
        }

        $ox = $offset['x'] ?? 0;
        $oy = $offset['y'] ?? 0;

        $pdf->SetLineWidth(0.15);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetTextColor(0, 0, 0);

        // mm rulers along the top and left edges (ticks every 1mm, tall every 10mm).
        for ($x = 10; $x <= $sheet->sheet_w_mm - 10; $x++) {
            $len = $x % 10 === 0 ? 4 : 2;
            $pdf->Line($x + $ox, 3 + $oy, $x + $ox, 3 + $len + $oy);
            if ($x % 10 === 0) {
                $pdf->SetFont('helvetica', '', 5);
                $pdf->SetXY($x - 3 + $ox, 7.5 + $oy);
                $pdf->Cell(6, 2, (string) $x, 0, 0, 'C');
            }
        }
        for ($y = 10; $y <= $sheet->sheet_h_mm - 10; $y++) {
            $len = $y % 10 === 0 ? 4 : 2;
            $pdf->Line(3 + $ox, $y + $oy, 3 + $len + $ox, $y + $oy);
            if ($y % 10 === 0) {
                $pdf->SetFont('helvetica', '', 5);
                $pdf->SetXY(7.5 + $ox, $y - 1 + $oy);
                $pdf->Cell(8, 2, (string) $y, 0, 0, 'L');
            }
        }

        // Slot outlines + corner crosshairs + slot numbers.
        foreach ($sheet->slotPositions() as $slot => $pos) {
            $x = $pos['x'] + $ox;
            $y = $pos['y'] + $oy;
            $w = $sheet->card_w_mm;
            $h = $sheet->card_h_mm;

            $pdf->SetLineWidth(0.1);
            $pdf->Rect($x, $y, $w, $h);

            foreach ([[$x, $y], [$x + $w, $y], [$x, $y + $h], [$x + $w, $y + $h]] as [$cx, $cy]) {
                $pdf->SetLineWidth(0.2);
                $pdf->Line($cx - 3, $cy, $cx + 3, $cy);
                $pdf->Line($cx, $cy - 3, $cx, $cy + 3);
            }

            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetXY($x, $y + $h / 2 - 3);
            $pdf->Cell($w, 6, (string) $slot, 0, 0, 'C');
        }

        // Side banner.
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(0, $sheet->sheet_h_mm - 8);
        $pdf->Cell($sheet->sheet_w_mm, 5, strtoupper($side).' — CALIBRATION — print at 100% (Actual size)', 0, 0, 'C');

        if ($pageTransform) {
            $pdf->StopTransform();
        }

        return $pdf->Output('calibration.pdf', 'S');
    }

    /** Fresh sheet-sized TCPDF with print-exact settings. */
    private function newSheetPdf(SheetTemplate $sheet): TCPDF
    {
        $orient = $sheet->sheet_w_mm >= $sheet->sheet_h_mm ? 'L' : 'P';

        $pdf = new TCPDF($orient, 'mm', [$sheet->sheet_w_mm, $sheet->sheet_h_mm], true, 'UTF-8', false);
        $pdf->SetCreator('EduID');
        $pdf->SetTitle("ID Card Sheet — {$sheet->name}");
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        // Preserve crisp 300 DPI raster placement; never downsample card art.
        $pdf->setImageScale(1);
        $pdf->AddPage();

        return $pdf;
    }
}
