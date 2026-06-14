<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Guardian extends Model
{
    use HasFactory, BelongsToSchool;

    protected $fillable = [
        'school_id',
        'full_name',
        'phone',
        'phone_alt',
        'email',
        'relationship',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardians')
                    ->withPivot('is_primary');
    }
}
