<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One period of study: a student in a cohort, from a start date until an end
 * date. Promotion closes a row and opens a new one — it never rewrites history.
 */
class StudentEnrollment extends Model
{
    use HasFactory, BelongsToSchool;

    protected $fillable = [
        'school_id',
        'student_id',
        'class_id',
        'programme_id',
        'academic_year',
        'term_label',
        'level_number',
        'enrolled_on',
        'ended_on',
        'status',
        'exit_reason',
        'exit_note',
        'destination_school',
        'is_current',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_on'  => 'date',
            'ended_on'     => 'date',
            'is_current'   => 'boolean',
            'level_number' => 'integer',
            'meta'         => 'array',
        ];
    }

    // ── Relationships ────────────────────────────────────────────

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(LifecycleEvent::class, 'enrollment_id');
    }

    // ── Presentation helpers ─────────────────────────────────────

    /** "Grade 9 B · 2026/2027" — what to show in a history table. */
    public function label(): string
    {
        $parts = array_filter([
            $this->cohort ? trim($this->cohort->grade . ' ' . $this->cohort->section) : null,
            $this->academic_year,
            $this->term_label,
        ]);

        return $parts ? implode(' · ', $parts) : 'Not placed in a class yet';
    }

    public function durationLabel(): string
    {
        $start = $this->enrolled_on;
        if (!$start) {
            return '—';
        }

        $end = $this->ended_on ?? Carbon::now();
        $months = (int) $start->diffInMonths($end);

        if ($months < 1) {
            return $start->format('d M Y');
        }

        $years = intdiv($months, 12);
        $rem   = $months % 12;
        $bits  = [];
        if ($years) {
            $bits[] = $years . 'y';
        }
        if ($rem) {
            $bits[] = $rem . 'm';
        }

        return implode(' ', $bits);
    }

    public function isOpen(): bool
    {
        return $this->ended_on === null && $this->status === 'enrolled';
    }
}
