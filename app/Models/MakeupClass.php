<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

/**
 * A one-off, date-specific lesson outside the recurring weekly timetable
 * (see the create_makeup_classes migration for the rationale).
 */
class MakeupClass extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'class_id', 'subject_id', 'teacher_id',
        'date', 'start_time', 'end_time', 'room', 'reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
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
