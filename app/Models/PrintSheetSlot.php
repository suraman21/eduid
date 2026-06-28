<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintSheetSlot extends Model
{
    protected $fillable = [
        'print_sheet_id', 'slot_number', 'status', 'student_id',
        'card_order_id', 'printed_front', 'printed_back', 'printed_at',
    ];

    protected function casts(): array
    {
        return [
            'slot_number'   => 'integer',
            'printed_front' => 'boolean',
            'printed_back'  => 'boolean',
            'printed_at'    => 'datetime',
        ];
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(PrintSheet::class, 'print_sheet_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isEmpty(): bool
    {
        return $this->status === 'empty';
    }
}
