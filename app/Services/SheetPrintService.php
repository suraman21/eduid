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
    ): string {
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

        $positions = $sheet->slotPositions();

        foreach ($assignments as $a) {
            $pos = $positions[$a['slot']] ?? null;
            if (! $pos) {
                continue;
            }

            /** @var Student $student */
            $student = $a['student'];
            $this->cards->ensureToken($student, $school);

            $pdf->StartTransform();
            $pdf->Translate($pos['x'] + ($offset['x'] ?? 0), $pos['y'] + ($offset['y'] ?? 0));
            // Render the card at the slot's exact physical size (CR80) so every
            // design fills its slot regardless of the source canvas resolution.
            $this->cards->drawAnyCardSide($pdf, $student, $school, $template, $side, $sheet->card_w_mm, $sheet->card_h_mm);
            $pdf->StopTransform();
        }

        return $pdf->Output('sheet.pdf', 'S');
    }
}
