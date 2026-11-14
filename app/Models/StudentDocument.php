<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file attached to a student. Stored on the private disk and served only
 * through an authenticated, tenant-scoped route.
 */
class StudentDocument extends Model
{
    use HasFactory, BelongsToSchool;

    protected $fillable = [
        'school_id',
        'student_id',
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
        'birth_certificate'   => 'Birth certificate',
        'previous_report_card' => 'Previous report card',
        'photo_id'            => 'Photo ID',
        'medical_record'      => 'Medical record',
        'transfer_letter'     => 'Transfer letter',
        'guardian_id'         => 'Guardian ID',
        'admission_letter'    => 'Admission letter',
        'certificate'         => 'Certificate',
        'other'               => 'Other',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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
