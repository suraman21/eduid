<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class AlertOutbox extends Model
{
    use BelongsToSchool;

    protected $table = 'alert_outbox';

    protected $fillable = [
        'school_id', 'guardian_id', 'student_id', 'session_id',
        'channel', 'message', 'status', 'attempts', 'last_error', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'sent_at'  => 'datetime',
        ];
    }

    public function guardian()
    {
        return $this->belongsTo(Guardian::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
