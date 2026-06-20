<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'payments';

    protected $fillable = [
        'school_id',
        'plan_id',
        'amount_etb',
        'payment_method',
        'bank_name',
        'reference_number',
        'status',
        'recorded_by',
        'payment_date',
        'period_months',
        'notes',
        'receipt_path',
        'confirmed_at',
        'confirmed_by',
    ];

    protected $casts = [
        'amount_etb' => 'decimal:2',
        'payment_date' => 'date',
        'confirmed_at' => 'datetime',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }
}
