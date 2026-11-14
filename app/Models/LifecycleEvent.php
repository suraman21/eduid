<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit of a student's journey. Never updated, never deleted.
 */
class LifecycleEvent extends Model
{
    use HasFactory, BelongsToSchool;

    protected $fillable = [
        'school_id',
        'student_id',
        'enrollment_id',
        'event',
        'occurred_on',
        'from_label',
        'to_label',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
        ];
    }

    public const LABELS = [
        'admitted'         => 'Admitted',
        'enrolled'         => 'Enrolled',
        'class_changed'    => 'Class changed',
        'promoted'         => 'Promoted',
        'repeated'         => 'Repeated year',
        'transferred_in'   => 'Transferred in',
        'transferred_out'  => 'Transferred out',
        'withdrawn'        => 'Withdrawn',
        'suspended'        => 'Suspended',
        'reinstated'       => 'Reinstated',
        'graduated'        => 'Graduated',
        'completed'        => 'Completed programme',
        'details_updated'  => 'Details updated',
        'document_added'   => 'Document added',
    ];

    public const EXIT_EVENTS = [
        'transferred_out', 'withdrawn', 'graduated', 'suspended', 'completed',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'enrollment_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function label(): string
    {
        return self::LABELS[$this->event] ?? ucfirst(str_replace('_', ' ', $this->event));
    }
}
