<?php

namespace Tests\Unit;

use App\Models\CardTemplate;
use PHPUnit\Framework\TestCase;

/**
 * Guards the fix for the "card prints at 153×216 mm" bug: physical card size is
 * CR80, decoupled from the (possibly high-resolution) design canvas.
 */
class CardTemplateSizingTest extends TestCase
{
    public function test_high_res_portrait_canvas_still_reports_cr80(): void
    {
        // ~1808×2559 px design (what blew up to 153×216 mm before the fix).
        $t = new CardTemplate(['canvas_w' => 1808, 'canvas_h' => 2559]);

        $this->assertTrue($t->isPortrait());
        $this->assertEqualsWithDelta(53.98, $t->cardWMm(), 0.01);
        $this->assertEqualsWithDelta(85.6, $t->cardHMm(), 0.01);
    }

    public function test_landscape_canvas_reports_cr80_landscape(): void
    {
        $t = new CardTemplate(['canvas_w' => 2024, 'canvas_h' => 1276]);

        $this->assertFalse($t->isPortrait());
        $this->assertEqualsWithDelta(85.6, $t->cardWMm(), 0.01);
        $this->assertEqualsWithDelta(53.98, $t->cardHMm(), 0.01);
    }

    public function test_explicit_physical_size_is_respected(): void
    {
        $t = new CardTemplate(['canvas_w' => 1808, 'canvas_h' => 2559, 'card_width_mm' => 60, 'card_height_mm' => 90]);

        $this->assertEqualsWithDelta(60.0, $t->cardWMm(), 0.01);
        $this->assertEqualsWithDelta(90.0, $t->cardHMm(), 0.01);
    }

    /**
     * THE root cause of "portrait prints landscape": card_width_mm/height_mm
     * carry a landscape 85.6×54 DB default on every row, and the physical size
     * used to trust that order verbatim. The canvas must decide orientation —
     * stored dims only supply the long/short edge lengths.
     */
    public function test_landscape_default_columns_do_not_override_portrait_canvas(): void
    {
        $t = new CardTemplate([
            'canvas_w' => 638, 'canvas_h' => 1011,           // portrait design
            'card_width_mm' => 85.60, 'card_height_mm' => 54, // stale landscape default
        ]);

        $this->assertEqualsWithDelta(54.0, $t->cardWMm(), 0.01);
        $this->assertEqualsWithDelta(85.6, $t->cardHMm(), 0.01);
        $this->assertTrue($t->cardHMm() > $t->cardWMm(), 'portrait canvas must yield portrait physical dims');
    }

    public function test_portrait_stored_dims_orient_to_landscape_canvas(): void
    {
        $t = new CardTemplate([
            'canvas_w' => 1011, 'canvas_h' => 638,            // landscape design
            'card_width_mm' => 54, 'card_height_mm' => 85.60, // stored the other way round
        ]);

        $this->assertEqualsWithDelta(85.6, $t->cardWMm(), 0.01);
        $this->assertEqualsWithDelta(54.0, $t->cardHMm(), 0.01);
    }

    public function test_native_canvas_mm_is_the_design_space(): void
    {
        $t = new CardTemplate(['canvas_w' => 1808, 'canvas_h' => 2559]);

        // Native size is still px ÷ 300 DPI — the coordinate space fields live in.
        $this->assertEqualsWithDelta(153.06, $t->nativeWMm(), 0.1);
        $this->assertEqualsWithDelta(216.66, $t->nativeHMm(), 0.1);
    }
}
