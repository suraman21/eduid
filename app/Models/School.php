<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'logo_path',
        'address',
        'phone',
        'email',
        'calendar_system',
        'academic_year',
        'hmac_secret',
        'is_active',
        'plan_tier',
        'max_students',
        'max_teachers',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'max_students' => 'integer',
            'max_teachers' => 'integer',
        ];
    }

    // ── Relationships ──────────────────────────────────────────

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    public function cardOrders(): HasMany
    {
        return $this->hasMany(CardOrder::class);
    }

    public function photoSessions(): HasMany
    {
        return $this->hasMany(PhotoSession::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    // ── Helpers ─────────────────────────────────────────────────

    /**
     * Get logo URL via Storage facade.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::url($this->logo_path);
    }

    /**
     * Check if the school has reached its student limit.
     */
    public function hasReachedStudentLimit(): bool
    {
        return $this->students()->where('is_active', true)->count() >= $this->max_students;
    }

    /**
     * Check if the school has reached its teacher limit.
     */
    public function hasReachedTeacherLimit(): bool
    {
        return $this->users()->where('role', 'teacher')->where('is_active', true)->count() >= $this->max_teachers;
    }

    /**
     * Generate a new HMAC secret for this school.
     */
    public static function generateHmacSecret(): string
    {
        return bin2hex(random_bytes(32));
    }
}
