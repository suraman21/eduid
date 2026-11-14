<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An issued transcript: a frozen snapshot plus a serial the school can be
 * asked about years later.
 */
class Transcript extends Model
{
    use HasFactory, BelongsToSchool;

    protected $fillable = [
        'school_id',
        'student_id',
        'reference',
        'purpose',
        'issued_on',
        'snapshot',
        'note',
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

    public const PURPOSES = [
        'records'       => 'Official copy',
        'transfer'      => 'School transfer',
        'graduation'    => 'Graduation / completion',
        'employment'    => 'Employment',
        'further_study' => 'Further study',
        'other'         => 'Other',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function purposeLabel(): string
    {
        return self::PURPOSES[$this->purpose] ?? ucfirst($this->purpose);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
