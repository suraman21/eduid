<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'classes';

    protected $fillable = [
        'school_id',
        'grade',
        'section',
        'academic_year',
        'level_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'level_order' => 'integer',
        ];
    }

    // ── Relationships ──────────────────────────────────────────

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'class_id');
    }

    public function teachers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'teacher_classes', 'class_id', 'user_id');
    }

    // ── Helpers ─────────────────────────────────────────────────

    /**
     * Get display name like "Grade 9 - A"
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->grade} - {$this->section}";
    }

    /**
     * Get active student count for this class.
     */
    public function activeStudentCount(): int
    {
        return $this->students()->where('status', 'active')->count();
    }
}
