<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An issued certificate — a letter the school has put its name to.
 *
 * The rendered wording lives in `snapshot`, frozen at the moment of issue, so
 * what the family holds is exactly what the school issued.
 */
class Certificate extends Model
{
    use HasFactory, BelongsToSchool;

    protected $fillable = [
        'school_id',
        'student_id',
        'reference',
        'type',
        'addressed_to',
        'issued_on',
        'snapshot',
        'body_note',
        'issued_by',
        'revoked_at',
        'revoked_reason',
    ];

    protected function casts(): array
    {
        return [
            'issued_on'  => 'date',
            'snapshot'   => 'array',
            'revoked_at' => 'datetime',
        ];
    }

    public const TYPES = [
        'transfer'   => 'Transfer certificate',
        'completion' => 'Certificate of completion',
        'character'  => 'Character / testimonial',
        'bonafide'   => 'Bonafide student letter',
        'custom'     => 'Custom letter',
    ];

    /** One line explaining when a registrar would reach for each. */
    public const TYPE_HINTS = [
        'transfer'   => 'The student is leaving — confirms dates of study and last class reached.',
        'completion' => 'The student finished a level at this school.',
        'character'  => 'A conduct reference for a new school or an employer.',
        'bonafide'   => 'Proof the student is currently enrolled — visas, banks, sponsors.',
        'custom'     => 'Anything else, written in your own words.',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
