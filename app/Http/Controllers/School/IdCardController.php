<?php

namespace App\Http\Controllers\School;

use App\Models\CardOrder;
use App\Models\Student;
use TCPDF2DBarcode;

/**
 * Web-viewable student ID cards (no server-side PDF — PRD Golden Rule).
 * Browser print produces physical-card-sized output via CSS @page rules.
 */
class IdCardController extends BaseSchoolController
{
    /**
     * Preview a single student's ID card.
     */
    public function show(Student $student)
    {
        $this->requireTeacherCanAccessClass($student->class_id);

        $school = $this->currentSchool();
        $student->load('schoolClass');

        $qrSvg = $this->qrSvg($this->qrPayload($student));

        return view('school.id_cards.show', compact('student', 'school', 'qrSvg'));
    }

    /**
     * Batch preview of all cards in a card order — for print-before-ordering review.
     */
    public function batch(CardOrder $cardOrder)
    {
        $this->requireAdmin();

        $school   = $this->currentSchool();
        $students = Student::with('schoolClass')
            ->whereIn('id', $cardOrder->student_ids ?? [])
            ->orderBy('first_name')
            ->get()
            ->map(fn (Student $s) => [
                'student' => $s,
                'qrSvg'   => $this->qrSvg($this->qrPayload($s)),
            ]);

        return view('school.id_cards.batch', compact('students', 'school', 'cardOrder'));
    }

    /**
     * The QR payload embedded in each card: "UID|hmac_token"
     * Matches the format expected by QrTokenService::verifyPayloadForSchool().
     */
    private function qrPayload(Student $student): string
    {
        return $student->uid . '|' . ($student->qr_token ?? '');
    }

    /**
     * Render a QR code as an inline SVG string using TCPDF's 2D barcode engine.
     * No external images, no CDN — fully self-contained, CSP-safe.
     */
    private function qrSvg(string $data, int $cells = 21): string
    {
        $barcode = new TCPDF2DBarcode($data, 'QRCODE,M');
        $grid    = $barcode->getBarcodeArray();

        if (empty($grid) || empty($grid['bcode'])) {
            return '';
        }

        $rows = (int) $grid['num_rows'];
        $cols = (int) $grid['num_cols'];

        // Build path data — one "M x y h 1 v 1 h -1 Z" rect per dark cell.
        $path = '';
        foreach ($grid['bcode'] as $y => $row) {
            foreach ($row as $x => $cell) {
                if ($cell) {
                    $path .= "M{$x},{$y}h1v1h-1Z";
                }
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" '
            . 'shape-rendering="crispEdges" style="width:100%%;height:100%%">'
            . '<rect width="%d" height="%d" fill="white"/>'
            . '<path d="%s" fill="black"/>'
            . '</svg>',
            $cols, $rows, $cols, $rows, $path
        );
    }
}
