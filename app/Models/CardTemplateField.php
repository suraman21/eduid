<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardTemplateField extends Model
{
    use HasFactory;

    protected $table = 'card_template_fields';

    protected $fillable = [
        'template_id',
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

    protected $casts = [
        'x_mm' => 'decimal:2',
        'y_mm' => 'decimal:2',
        'width_mm' => 'decimal:2',
        'height_mm' => 'decimal:2',
        'font_size' => 'integer',
        'z_index' => 'integer',
    ];

    public function template()
    {
        return $this->belongsTo(CardTemplate::class, 'template_id');
    }
}
