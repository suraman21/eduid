<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GradeSubmission extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'grade_submissions';

    protected $fillable = [
        'school_id',
        'class_id',
        'subject_id',
        'term_id',
        'submitted_by',
        'submitted_at',
        'homeroom_approved_by',
        'homeroom_approved_at',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'homeroom_approved_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function homeroomApprovedBy()
    {
        return $this->belongsTo(User::class, 'homeroom_approved_by');
    }

    public function publishedBy()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function isHomeroomApproved(): bool
    {
        return $this->homeroom_approved_at !== null;
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
