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

    /** Per-card price in ETB by order type (PRD §11). first_batch is included. */
    public const PRICING = [
        'first_batch'    => 0,
        'annual_renewal' => 50,
        'replacement'    => 60,
        'new_student'    => 60,
    ];

    /** Order types a school may place itself (first_batch is system-generated). */
    public const SCHOOL_ORDER_TYPES = ['annual_renewal', 'replacement', 'new_student'];

    /** Statuses that still occupy a student in an "open" order (for dup checks). */
    public const OPEN_STATUSES = ['pending', 'confirmed', 'printing', 'shipped'];

    /**
     * Per-card price in ETB for a given order type.
     */
    public static function unitPrice(string $orderType): int
    {
        return self::PRICING[$orderType] ?? 0;
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
