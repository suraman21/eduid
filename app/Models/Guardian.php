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
        'user_id',
        'full_name',
        'phone',
        'phone_alt',
        'email',
        'telegram_chat_id',
        'relationship',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardians')
                    ->withPivot('is_primary');
    }

    /** Login account for the parent app (null = record only, no login). */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
