<?php

namespace Database\Seeders;

use App\Models\SheetTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds the built-in sheet layouts. Idempotent (updateOrCreate by key) so it is
 * safe to run on every deploy. Geometry values are sensible starting points for
 * a 2×5 CR80 A4 sheet; the real per-batch alignment is handled by printer
 * calibration, not by editing these.
 */
class SheetTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // A4 (210×297) holding 10 CR80 cards as 2 columns × 5 rows, centred.
        // Horizontal: 13 + 85.6 + 12.8 + 85.6 + 13 = 210
        // Vertical:   8 + 5×53.98 + 4×2.775 + 8 = 297
        SheetTemplate::updateOrCreate(
            ['key' => 'a4-10up'],
            [
                'name'           => 'A4 PVC (10 Cards)',
                'sheet_w_mm'     => 210,
                'sheet_h_mm'     => 297,
                'columns'        => 2,
                'rows'           => 5,
                'card_w_mm'      => 85.6,
                'card_h_mm'      => 53.98,
                'margin_top_mm'  => 8,
                'margin_left_mm' => 13,
                'h_spacing_mm'   => 12.8,
                'v_spacing_mm'   => 2.775,
                'is_active'      => true,
            ]
        );
    }
}
