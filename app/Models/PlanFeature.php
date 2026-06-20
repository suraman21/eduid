<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanFeature extends Model
{
    use HasFactory;

    protected $table = 'plan_features';

    protected $fillable = [
        'plan_tier',
        'feature_key',
    ];

    public function featureRegistry()
    {
        return $this->belongsTo(FeatureRegistry::class, 'feature_key', 'key');
    }
}
