<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoSession extends Model
{
    use HasFactory, BelongsToSchool;

    protected $fillable = [
        'school_id',
        'created_by',
        'class_id',
        'session_type',
        'status',
        'student_queue',
        'total_students',
        'photos_captured',
        'current_position',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'student_queue'   => 'array',
            'total_students'  => 'integer',
            'photos_captured' => 'integer',
            'current_position' => 'integer',
            'completed_at'    => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    // ── Helpers ─────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Get the current student in the queue.
     */
    public function getCurrentStudent(): ?Student
    {
        $queue = $this->student_queue ?? [];

        if ($this->current_position >= count($queue)) {
            return null;
        }

        return Student::find($queue[$this->current_position]);
    }

    /**
     * Get progress percentage.
     */
    public function getProgressAttribute(): float
    {
        if ($this->total_students === 0) return 0;
        return round(($this->photos_captured / $this->total_students) * 100, 1);
    }

    /**
     * Check if session has been inactive for more than 7 days (auto-expiry).
     */
    public function isExpired(): bool
    {
        return $this->isActive()
            && $this->updated_at
            && $this->updated_at->diffInDays(now()) >= 7;
    }
}
