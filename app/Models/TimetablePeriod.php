<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class TimetablePeriod extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'label', 'start_time', 'end_time', 'is_break', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_break' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function entries()
    {
        return $this->hasMany(TimetableEntry::class, 'period_id');
    }
}
