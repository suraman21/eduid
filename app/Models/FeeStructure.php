<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

/** A reusable charge definition the school can bill students from. */
class FeeStructure extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'name', 'amount_etb', 'frequency',
        'applies_to', 'target_class_id', 'academic_year', 'is_active',
    ];

    protected $casts = [
        'amount_etb' => 'decimal:2',
        'is_active'  => 'boolean',
    ];

    public function targetClass()
    {
        return $this->belongsTo(SchoolClass::class, 'target_class_id');
    }

    public function invoices()
    {
        return $this->hasMany(StudentInvoice::class);
    }
}
