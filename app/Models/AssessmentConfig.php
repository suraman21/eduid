<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssessmentConfig extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'assessment_configs';

    protected $fillable = [
        'school_id',
        'academic_year',
        'grade_level',
        'ca_weight',
        'midterm_weight',
        'final_weight',
    ];

    protected $casts = [
        'ca_weight' => 'decimal:2',
        'midterm_weight' => 'decimal:2',
        'final_weight' => 'decimal:2',
    ];

    public function validate(): bool
    {
        $total = (float) $this->ca_weight + (float) $this->midterm_weight + (float) $this->final_weight;
        return abs($total - 100.0) < 0.01;
    }
}
