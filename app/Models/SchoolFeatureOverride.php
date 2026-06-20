<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolFeatureOverride extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'school_feature_overrides';

    protected $fillable = [
        'school_id',
        'feature_key',
        'is_enabled',
        'overridden_by',
        'overridden_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'overridden_at' => 'datetime',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function overriddenBy()
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }
}
