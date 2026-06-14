<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardOrder extends Model
{
    use HasFactory, BelongsToSchool;

    protected $fillable = [
        'school_id',
        'order_code',
        'ordered_by',
        'student_ids',
        'student_count',
        'order_type',
        'status',
        'cancel_reason',
        'print_file_path',
        'amount_etb',
        'tracking_note',
        'confirmed_at',
        'printed_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'student_ids'  => 'array',
            'amount_etb'   => 'decimal:2',
            'confirmed_at' => 'datetime',
            'printed_at'   => 'datetime',
            'shipped_at'   => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    // ── Status Helpers ──────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    /**
     * Can this order be cancelled?
     * School Admin: only pending.
     * Super Admin: pending or confirmed.
     */
    public function canBeCancelledBy(User $user): bool
    {
        if ($user->role === 'super_admin') {
            return in_array($this->status, ['pending', 'confirmed']);
        }

        return $this->status === 'pending';
    }

    /**
     * Get the students included in this order.
     */
    public function getStudents()
    {
        return Student::whereIn('id', $this->student_ids ?? [])->get();
    }

    /**
     * Status badge color for UI.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending'   => 'yellow',
            'confirmed' => 'blue',
            'printing'  => 'indigo',
            'shipped'   => 'purple',
            'delivered' => 'green',
            'cancelled' => 'red',
            default     => 'gray',
        };
    }
}
