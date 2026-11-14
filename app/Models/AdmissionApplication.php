<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's journey from enquiry to student.
 */
class AdmissionApplication extends Model
{
    use HasFactory, BelongsToSchool;

    protected $fillable = [
        'school_id',
        'reference',
        'first_name', 'last_name', 'first_name_am', 'last_name_am',
        'gender', 'date_of_birth',
        'phone', 'email', 'address_line',
        'guardian_name', 'guardian_phone', 'guardian_email', 'guardian_relationship',
        'previous_school', 'previous_grade',
        'programme_id', 'class_id', 'academic_year', 'intended_level',
        'status', 'source',
        'notes', 'decision_note', 'follow_up_on',
        'handled_by', 'decided_at',
        'student_id', 'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'follow_up_on'  => 'date',
            'decided_at'    => 'datetime',
            'converted_at'  => 'datetime',
        ];
    }

    public const STATUSES = [
        'enquiry'   => 'Enquiry',
        'applied'   => 'Applied',
        'screening' => 'Screening',
        'offered'   => 'Offered',
        'accepted'  => 'Accepted',
        'rejected'  => 'Rejected',
        'withdrawn' => 'Withdrawn',
        'converted' => 'Enrolled',
    ];

    /** Colours for the pipeline UI. */
    public const STATUS_COLORS = [
        'enquiry'   => ['#f1f5f9', '#475569'],
        'applied'   => ['#dbeafe', '#1d4ed8'],
        'screening' => ['#fef3c7', '#b45309'],
        'offered'   => ['#e0e7ff', '#4338ca'],
        'accepted'  => ['#d1fae5', '#047857'],
        'rejected'  => ['#fee2e2', '#b91c1c'],
        'withdrawn' => ['#f3f4f6', '#6b7280'],
        'converted' => ['#dcfce7', '#15803d'],
    ];

    public const SOURCES = [
        'walk_in'  => 'Walk-in',
        'phone'    => 'Phone',
        'website'  => 'Website',
        'referral' => 'Referral',
        'social'   => 'Social media',
        'event'    => 'Event',
        'other'    => 'Other',
    ];

    /** Statuses that should no longer appear in the "active pipeline". */
    public const CLOSED = ['rejected', 'withdrawn', 'converted'];

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function fullNameAm(): ?string
    {
        if (!$this->first_name_am || !$this->last_name_am) {
            return null;
        }

        return "{$this->first_name_am} {$this->last_name_am}";
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? ucfirst($this->source);
    }

    public function isOpen(): bool
    {
        return !in_array($this->status, self::CLOSED, true);
    }

    /** Days since the application was created — the "how long have they waited" number. */
    public function ageInDays(): int
    {
        return (int) $this->created_at?->diffInDays(now());
    }

    // ── Relationships ────────────────────────────────────────────

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
