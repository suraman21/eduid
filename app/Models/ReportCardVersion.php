<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ReportCardVersion extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'report_card_versions';

    protected $fillable = [
        'school_id',
        'student_id',
        'term_id',
        'version_number',
        'pdf_path',
        'generated_at',
        'generated_by',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'generated_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function getPdfUrl(): string
    {
        return Storage::url($this->pdf_path);
    }
}
