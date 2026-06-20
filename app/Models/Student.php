<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory, BelongsToSchool;

    protected $fillable = [
        'school_id',
        'uid',
        'first_name',
        'last_name',
        'first_name_am',
        'last_name_am',
        'gender',
        'date_of_birth',
        'class_id',
        'photo_path',
        'blood_type',
        'medical_notes',
        'enrollment_date',
        'is_active',
        'qr_token',
        'qr_token_year',
        'status',
        'join_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth'   => 'date',
            'enrollment_date' => 'date',
            'is_active'       => 'boolean',
            'status'          => 'string',
            'join_date'       => 'date',
            'end_date'        => 'date',
        ];
    }

    // ── Relationships ──────────────────────────────────────────

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardians')
                    ->withPivot('is_primary');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    // ── Helpers ─────────────────────────────────────────────────

    /**
     * Full name in English.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Full name in Amharic (if available).
     */
    public function getFullNameAmAttribute(): ?string
    {
        if (!$this->first_name_am || !$this->last_name_am) {
            return null;
        }
        return "{$this->first_name_am} {$this->last_name_am}";
    }

    /**
     * Get photo URL via Storage facade (S3-ready).
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo_path) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::url($this->photo_path);
    }

    /**
     * Check if student has a photo.
     */
    public function hasPhoto(): bool
    {
        return !empty($this->photo_path)
            && \Illuminate\Support\Facades\Storage::exists($this->photo_path);
    }

    /**
     * Get primary guardian.
     */
    public function primaryGuardian(): ?Guardian
    {
        return $this->guardians()->wherePivot('is_primary', true)->first();
    }

    /**
     * Check if QR token is valid for the current academic year.
     */
    public function hasValidQrToken(): bool
    {
        if (!$this->qr_token || !$this->qr_token_year) {
            return false;
        }

        return $this->qr_token_year === $this->school->academic_year;
    }

    /**
     * Check if the student's status is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the student was enrolled on a given date.
     */
    public function isEnrolledOn(Carbon $date): bool
    {
        return ($this->join_date === null || $date->gte($this->join_date))
            && ($this->end_date === null || $date->lte($this->end_date));
    }
}
