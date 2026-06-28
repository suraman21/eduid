<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class TimetableEntry extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'class_id', 'period_id', 'day_of_week',
        'subject_id', 'teacher_id', 'room',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    public function period()
    {
        return $this->belongsTo(TimetablePeriod::class, 'period_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
