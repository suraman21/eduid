<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeatureRegistry extends Model
{
    use HasFactory;

    protected $table = 'feature_registry';

    protected $fillable = [
        'key',
        'label',
        'description',
        'category',
        'is_default_enabled',
    ];

    protected $casts = [
        'is_default_enabled' => 'boolean',
    ];
}
