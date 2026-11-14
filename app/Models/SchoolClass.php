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
        'programme_id',
        'grade',
        'section',
        'academic_year',
        'cohort_kind',
        'level_number',
        'shift',
        'capacity',
        'start_date',
        'end_date',
        'level_order',
        'is_active',
        'homeroom_teacher_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'level_order'  => 'integer',
            'level_number' => 'integer',
            'capacity'     => 'integer',
            'start_date'   => 'date',
            'end_date'     => 'date',
        ];
    }

    public const SHIFTS = [
        'full_day'  => 'Full day',
        'morning'   => 'Morning',
        'afternoon' => 'Afternoon',
        'evening'   => 'Evening',
        'weekend'   => 'Weekend',
    ];

    public const KINDS = [
        'grade_section' => 'Grade section',
        'semester'      => 'Semester',
        'batch'         => 'Batch',
        'intake'        => 'Intake',
        'group'         => 'Group',
    ];

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

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeroom_teacher_id');
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class, 'class_id');
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class, 'class_id');
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
     * Richer label used by the lifecycle UI, e.g. "Grade 9 A · 2026/2027 · Morning".
     */
    public function getLifecycleLabelAttribute(): string
    {
        $base = trim("{$this->grade} {$this->section}");
        $bits = [$base];

        if ($this->academic_year) {
            $bits[] = $this->academic_year;
        }
        if ($this->shift && $this->shift !== 'full_day') {
            $bits[] = self::SHIFTS[$this->shift] ?? $this->shift;
        }

        return implode(' · ', $bits);
    }

    /**
     * Seats still open, or null when no capacity was set.
     */
    public function seatsRemaining(): ?int
    {
        if (!$this->capacity) {
            return null;
        }

        return max(0, $this->capacity - $this->enrollments()->where('is_current', true)->count());
    }

    /**
     * Get active student count for this class.
     */
    public function activeStudentCount(): int
    {
        return $this->students()->where('status', 'active')->count();
    }
}
