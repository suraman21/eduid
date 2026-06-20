<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GradingScale extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'grading_scales';

    protected $fillable = [
        'school_id',
        'label',
        'min_score',
        'max_score',
        'gpa_point',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'min_score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'gpa_point' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    /**
     * Get the letter grade label for a given score from this scale entry.
     * Usage: Load all scales for a school sorted by min_score desc, then call first match.
     */
    public function matches(float $score): bool
    {
        return $score >= (float) $this->min_score && $score <= (float) $this->max_score;
    }
}
