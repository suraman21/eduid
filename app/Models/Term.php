<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Term extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'terms';

    protected $fillable = [
        'school_id',
        'academic_year',
        'name',
        'start_date',
        'end_date',
        'is_current',
        'weight',
        'is_weight_locked',
        'order',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
        'weight' => 'decimal:2',
        'is_weight_locked' => 'boolean',
        'order' => 'integer',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function grades()
    {
        return $this->hasMany(StudentGrade::class);
    }

    public function gradeSubmissions()
    {
        return $this->hasMany(GradeSubmission::class);
    }

    public function isCurrent(): bool
    {
        return (bool) $this->is_current;
    }

    public function canModifyWeight(): bool
    {
        return ! $this->is_weight_locked;
    }
}
