<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'student_id',
        'status',
        'scanned_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at'  => 'datetime',
            'created_at'  => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
