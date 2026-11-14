<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A programme of study: one per stream for a school, one per award for a
 * college, one per course for a course centre.
 */
class Programme extends Model
{
    use HasFactory, BelongsToSchool;

    protected $fillable = [
        'school_id',
        'code',
        'name',
        'level_type',
        'award',
        'duration_months',
        'total_levels',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'       => 'boolean',
            'duration_months' => 'integer',
            'total_levels'    => 'integer',
        ];
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'programme_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class, 'programme_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
