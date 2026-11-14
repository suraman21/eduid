<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file held against a staff member — contract, licence, clearance, permit.
 *
 * Stored on the private disk and served only through an authenticated,
 * tenant-scoped route. Many of these expire, and an expired teaching licence
 * or work permit is a compliance problem, so expiry state is a first-class
 * concern rather than a date shown in passing.
 */
class StaffDocument extends Model
{
    use HasFactory, BelongsToSchool;

    protected $fillable = [
        'school_id',
        'user_id',
        'type',
        'title',
        'description',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'issued_on',
        'expires_on',
        'is_verified',
        'verified_by',
        'verified_at',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_on'   => 'date',
            'expires_on'  => 'date',
            'verified_at' => 'datetime',
            'is_verified' => 'boolean',
            'file_size'   => 'integer',
        ];
    }

    public const TYPES = [
        'contract'            => 'Employment contract',
        'teaching_licence'    => 'Teaching licence',
        'police_clearance'    => 'Police clearance',
        'medical_certificate' => 'Medical certificate',
        'qualification'       => 'Qualification / diploma',
        'national_id'         => 'National ID',
        'work_permit'         => 'Work permit',
        'reference_letter'    => 'Reference letter',
        'other'               => 'Other',
    ];

    /** Types where a lapse is a legal or safeguarding problem, not just tidiness. */
    public const RENEWABLE = [
        'teaching_licence', 'police_clearance', 'medical_certificate', 'work_permit', 'contract',
    ];

    /** Days before expiry at which the register starts shouting. */
    public const WARN_DAYS = 60;

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? 'Other';
    }

    /** expired | expiring | valid | none */
    public function expiryState(): string
    {
        if (! $this->expires_on) {
            return 'none';
        }

        if ($this->expires_on->isPast()) {
            return 'expired';
        }

        if ($this->expires_on->lte(now()->addDays(self::WARN_DAYS))) {
            return 'expiring';
        }

        return 'valid';
    }

    public function daysUntilExpiry(): ?int
    {
        return $this->expires_on ? (int) now()->startOfDay()->diffInDays($this->expires_on->startOfDay(), false) : null;
    }

    public function isExpired(): bool
    {
        return $this->expiryState() === 'expired';
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->file_size;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, 1) . ' ' . $unit;
            }
            $bytes /= 1024;
        }

        return round($bytes, 1) . ' TB';
    }
}
