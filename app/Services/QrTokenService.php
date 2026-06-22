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
     * Verify a scanned QR payload ("UID|token") against a known school.
     *
     * Used by the mobile scanner API where the school is already established
     * from the authenticated token, so we match on UID (auto-scoped) and then
     * constant-time compare the recomputed HMAC. Returns the matching Student,
     * or null if the payload is malformed, unknown, stale, or forged.
     */
    public function verifyPayloadForSchool(string $qrData, School $school): ?Student
    {
        if (! str_contains($qrData, '|')) {
            return null;
        }

        [$uid, $token] = array_pad(explode('|', $qrData, 2), 2, '');
        $uid   = trim($uid);
        $token = trim($token);

        if ($uid === '' || $token === '') {
            return null;
        }

        $student = Student::where('school_id', $school->id)
            ->where('uid', $uid)
            ->first();

        if (! $student) {
            return null;
        }

        $academicYear = $school->academic_year;
        $expected = hash_hmac('sha256', "{$uid}|{$academicYear}", $school->hmac_secret);

        // Token must be cryptographically valid AND minted for the current year.
        if (! hash_equals($expected, $token) || $student->qr_token_year !== $academicYear) {
            return null;
        }

        return $student;
    }

    /**
     * Regenerate (overwrite) the QR token for a student.
     */
    public function regenerate(Student $student, School $school): string
    {
        return $this->generate($student, $school);
    }
}
