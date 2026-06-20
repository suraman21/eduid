<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'subjects';

    protected $fillable = [
        'school_id',
        'name',
        'code',
        'color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function classSubjects()
    {
        return $this->hasMany(ClassSubject::class);
    }

    public function grades()
    {
        return $this->hasMany(StudentGrade::class);
    }
}
