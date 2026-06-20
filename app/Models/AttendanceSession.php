<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    use HasFactory, BelongsToSchool;

    protected $fillable = [
        'school_id',
        'class_id',
        'teacher_id',
        'session_date',
        'session_type',
        'method',
        'synced_from_app',
        'device_id',
        'idempotency_key',
        'recorded_on_behalf_of',
        'period_number',
    ];

    protected function casts(): array
    {
        return [
            'session_date'    => 'date',
            'synced_from_app' => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_on_behalf_of');
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'session_id');
    }

    // ── Helpers ─────────────────────────────────────────────────

    /**
     * Get present count for this session.
     */
    public function presentCount(): int
    {
        return $this->records()->where('status', 'present')->count();
    }

    /**
     * Get absent count for this session.
     */
    public function absentCount(): int
    {
        return $this->records()->where('status', 'absent')->count();
    }

    /**
     * Get late count for this session.
     */
    public function lateCount(): int
    {
        return $this->records()->where('status', 'late')->count();
    }

    /**
     * Get attendance rate as a percentage.
     */
    public function attendanceRate(): float
    {
        $total = $this->records()->count();
        if ($total === 0) return 0;

        $present = $this->records()->whereIn('status', ['present', 'late'])->count();
        return round(($present / $total) * 100, 1);
    }
}
