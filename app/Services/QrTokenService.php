<?php

namespace App\Services;

use App\Models\School;
use App\Models\Student;

class QrTokenService
{
    /**
     * Generate an HMAC-SHA256 QR token for a student and persist it.
     */
    public function generate(Student $student, School $school): string
    {
        $academicYear = $school->academic_year;
        $payload = "{$student->uid}|{$academicYear}";
        $token = hash_hmac('sha256', $payload, $school->hmac_secret);

        $student->update([
            'qr_token' => $token,
            'qr_token_year' => $academicYear,
        ]);

        return $token;
    }

    /**
     * Verify a QR token for a school code. Returns the matching Student or null.
     */
    public function verify(string $token, string $schoolCode): ?Student
    {
        $school = School::where('code', $schoolCode)->where('is_active', true)->first();

        if (! $school) {
            return null;
        }

        $academicYear = $school->academic_year;

        $student = Student::where('school_id', $school->id)
            ->where('qr_token', $token)
            ->where('qr_token_year', $academicYear)
            ->first();

        if (! $student) {
            return null;
        }

        // Recompute expected token to prevent token reuse from old years
        $payload = "{$student->uid}|{$academicYear}";
        $expected = hash_hmac('sha256', $payload, $school->hmac_secret);

        return hash_equals($expected, $token) ? $student : null;
    }

    /**
     * Regenerate (overwrite) the QR token for a student.
     */
    public function regenerate(Student $student, School $school): string
    {
        return $this->generate($student, $school);
    }
}
