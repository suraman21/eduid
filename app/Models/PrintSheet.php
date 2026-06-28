<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A physical PVC sheet's virtual record. Global super-admin infrastructure —
 * NOT tenant-scoped. Slots are filled sequentially, so occupied slots always
 * form a contiguous prefix; nextEmptySlot() is therefore where new cards go.
 */
class PrintSheet extends Model
{
    protected $fillable = [
        'sheet_template_id', 'school_id', 'status', 'created_by', 'notes',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(SheetTemplate::class, 'sheet_template_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(PrintSheetSlot::class)->orderBy('slot_number');
    }

    /** First empty slot number (where the next card goes), or null if full. */
    public function nextEmptySlot(): ?int
    {
        $min = $this->slots()->where('status', 'empty')->min('slot_number');

        return $min !== null ? (int) $min : null;
    }

    public function printedCount(): int
    {
        return $this->slots()->where('status', 'printed')->count();
    }

    public function isFull(): bool
    {
        return $this->nextEmptySlot() === null;
    }
}
