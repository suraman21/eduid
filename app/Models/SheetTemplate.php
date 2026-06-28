<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A physical card sheet layout (e.g. A4 PVC 10-up). Global infrastructure —
 * NOT tenant-scoped. Slot positions are computed from a regular grid so the
 * geometry stays editable; positions_json can override for irregular sheets.
 */
class SheetTemplate extends Model
{
    protected $fillable = [
        'key', 'name', 'sheet_w_mm', 'sheet_h_mm', 'columns', 'rows',
        'card_w_mm', 'card_h_mm', 'margin_top_mm', 'margin_left_mm',
        'h_spacing_mm', 'v_spacing_mm', 'positions_json', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sheet_w_mm'     => 'float',
            'sheet_h_mm'     => 'float',
            'columns'        => 'integer',
            'rows'           => 'integer',
            'card_w_mm'      => 'float',
            'card_h_mm'      => 'float',
            'margin_top_mm'  => 'float',
            'margin_left_mm' => 'float',
            'h_spacing_mm'   => 'float',
            'v_spacing_mm'   => 'float',
            'positions_json' => 'array',
            'is_active'      => 'boolean',
        ];
    }

    /** Total card slots on the sheet. */
    public function slotCount(): int
    {
        return $this->positions_json
            ? count($this->positions_json)
            : $this->columns * $this->rows;
    }

    /**
     * Top-left (x, y) in mm for every slot, keyed by 1-based slot number,
     * filled left→right, top→bottom (the order the cutting machine expects).
     *
     * @return array<int, array{x: float, y: float}>
     */
    public function slotPositions(): array
    {
        if ($this->positions_json) {
            $out = [];
            foreach (array_values($this->positions_json) as $i => $pos) {
                $out[$i + 1] = ['x' => (float) $pos['x'], 'y' => (float) $pos['y']];
            }

            return $out;
        }

        $positions = [];
        $slot = 1;
        for ($row = 0; $row < $this->rows; $row++) {
            for ($col = 0; $col < $this->columns; $col++) {
                $positions[$slot] = [
                    'x' => round($this->margin_left_mm + $col * ($this->card_w_mm + $this->h_spacing_mm), 3),
                    'y' => round($this->margin_top_mm + $row * ($this->card_h_mm + $this->v_spacing_mm), 3),
                ];
                $slot++;
            }
        }

        return $positions;
    }

    /** Position for a single slot, or null if out of range. */
    public function slotPosition(int $slot): ?array
    {
        return $this->slotPositions()[$slot] ?? null;
    }
}
