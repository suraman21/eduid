<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

/** A payment received against a student invoice (supports part-payment). */
class FeePayment extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'student_invoice_id', 'amount_etb',
        'method', 'reference', 'payment_date', 'note', 'recorded_by',
    ];

    protected $casts = [
        'amount_etb'   => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function invoice()
    {
        return $this->belongsTo(StudentInvoice::class, 'student_invoice_id');
    }
}
