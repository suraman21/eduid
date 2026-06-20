<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $table = 'subscription_plans';

    protected $fillable = [
        'name',
        'tier',
        'price_monthly_etb',
        'price_annual_etb',
        'max_students',
        'max_teachers',
        'max_school_admins',
        'description',
        'is_active',
    ];

    protected $casts = [
        'price_monthly_etb' => 'decimal:2',
        'price_annual_etb' => 'decimal:2',
        'is_active' => 'boolean',
        'max_students' => 'integer',
        'max_teachers' => 'integer',
        'max_school_admins' => 'integer',
    ];

    public function schools()
    {
        return $this->hasMany(School::class, 'plan_tier', 'tier');
    }
}
