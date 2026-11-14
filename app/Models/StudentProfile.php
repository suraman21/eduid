<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The extended student file: everything that is not needed to print an ID card.
 */
class StudentProfile extends Model
{
    use HasFactory, BelongsToSchool;

    protected $fillable = [
        'school_id',
        'student_id',
        // identity
        'national_id', 'birth_place', 'nationality', 'mother_tongue', 'religion',
        // address & contact
        'region', 'zone', 'woreda', 'kebele', 'house_number', 'address_line',
        'phone', 'email',
        // emergency
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
        // prior education
        'previous_school', 'previous_school_year', 'previous_grade', 'admission_source',
        // health
        'allergies', 'chronic_conditions', 'insurance_provider', 'insurance_number',
        'special_needs',
        // exit
        'exit_reason_category', 'exit_note', 'exit_destination',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
