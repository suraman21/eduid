<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

/**
 * A single charge owed by one student. The paid amount and balance are derived
 * live from the payment ledger — never stored — so they cannot drift.
 */
class StudentInvoice extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'student_id', 'fee_structure_id', 'title',
        'amount_etb', 'due_date', 'academic_year', 'status', 'note', 'created_by',
    ];

    protected $casts = [
        'amount_etb' => 'decimal:2',
        'due_date'   => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function payments()
    {
        return $this->hasMany(FeePayment::class);
    }

    /** Total received against this invoice. */
    public function paidAmount(): float
    {
        return (float) $this->payments()->sum('amount_etb');
    }

    /** Outstanding balance (never negative). */
    public function balance(): float
    {
        return max(0, (float) $this->amount_etb - $this->paidAmount());
    }

    /**
     * Recompute status from the ledger after a payment/adjustment. Never
     * overrides a manual 'waived' or 'void' — those are deliberate states.
     */
    public function syncStatus(): void
    {
        if (in_array($this->status, ['waived', 'void'], true)) {
            return;
        }

        $paid = $this->paidAmount();

        $this->status = match (true) {
            $paid <= 0                        => 'unpaid',
            $paid + 0.001 >= (float) $this->amount_etb => 'paid',
            default                           => 'partial',
        };
        $this->save();
    }
}
