<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportCardJob extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'report_card_jobs';

    protected $fillable = [
        'school_id',
        'class_id',
        'term_id',
        'status',
        'total_students',
        'processed',
        'failed_count',
        'output_zip_path',
        'created_by',
        'error_log',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'total_students' => 'integer',
        'processed' => 'integer',
        'failed_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isComplete(): bool
    {
        return $this->status === 'completed';
    }

    public function progressPercentage(): float
    {
        if ($this->total_students === 0) {
            return 0.0;
        }

        return round(($this->processed / $this->total_students) * 100, 2);
    }
}
