<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardTemplateField extends Model
{
    protected $table = 'card_template_fields';

    protected $fillable = [
        'template_id',
        'field_type',
        'field_key',
        'label',
        'x_mm',
        'y_mm',
        'width_mm',
        'height_mm',
        'font_size',
        'font_color',
        'font_family',
        'text_align',
        'font_weight',
        'z_index',
        'custom_value',
    ];

    protected function casts(): array
    {
        return [
            'x_mm'      => 'float',
            'y_mm'      => 'float',
            'width_mm'  => 'float',
            'height_mm' => 'float',
            'font_size' => 'integer',
            'z_index'   => 'integer',
        ];
    }

    /** Preview pixel coordinates (mm → px at 300 DPI). */
    public function getPxXAttribute(): int { return (int) round(CardTemplate::mmToPx($this->x_mm)); }
    public function getPxYAttribute(): int { return (int) round(CardTemplate::mmToPx($this->y_mm)); }
    public function getPxWAttribute(): ?int { return $this->width_mm  ? (int) round(CardTemplate::mmToPx($this->width_mm))  : null; }
    public function getPxHAttribute(): ?int { return $this->height_mm ? (int) round(CardTemplate::mmToPx($this->height_mm)) : null; }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CardTemplate::class, 'template_id');
    }
}
