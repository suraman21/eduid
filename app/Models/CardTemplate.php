<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardTemplate extends Model
{
    use HasFactory;

    protected $table = 'card_templates';

    protected $fillable = [
        'school_id',
        'name',
        'template_type',
        'scope',
        'front_image_path',
        'back_image_path',
        'card_width_mm',
        'card_height_mm',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'card_width_mm' => 'decimal:2',
        'card_height_mm' => 'decimal:2',
    ];

    public function fields()
    {
        return $this->hasMany(CardTemplateField::class, 'template_id');
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
