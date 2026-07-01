<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A physical printer's duplex calibration. Offsets are applied during PDF
 * generation so front/back registration matches this printer's paper feed.
 * Global super-admin infrastructure — not tenant-scoped.
 */
class PrinterProfile extends Model
{
    protected $fillable = [
        'name',
        'x_offset_front_mm', 'y_offset_front_mm',
        'x_offset_back_mm', 'y_offset_back_mm',
        'rotation_offset_deg', 'mirror_back', 'flip_mode', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'x_offset_front_mm'   => 'float',
            'y_offset_front_mm'   => 'float',
            'x_offset_back_mm'    => 'float',
            'y_offset_back_mm'    => 'float',
            'rotation_offset_deg' => 'float',
            'mirror_back'         => 'boolean',
            'is_default'          => 'boolean',
        ];
    }

    /** The calibration shift for one side, in mm. */
    public function offsetFor(string $side): array
    {
        return $side === 'back'
            ? ['x' => $this->x_offset_back_mm, 'y' => $this->y_offset_back_mm]
            : ['x' => $this->x_offset_front_mm, 'y' => $this->y_offset_front_mm];
    }

    /** Human flip instruction for the duplex pass. */
    public function flipInstruction(): string
    {
        return $this->flip_mode === 'short_edge'
            ? 'Flip the sheet like turning a CALENDAR page (over the short edge).'
            : 'Flip the sheet like turning a BOOK page (over the long edge).';
    }
}
