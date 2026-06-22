<?php

use App\Models\School;
use App\Models\Student;
use App\Services\QrTokenService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $service = new QrTokenService();

        School::with('students')->each(function (School $school) use ($service) {
            $year = substr($school->academic_year, 0, 4);
            $prefix = strtoupper($school->code) . '-' . $year . '-';

            $school->students()->each(function (Student $student) use ($school, $prefix, $service) {
                // Skip students already in the correct format
                if (str_starts_with($student->uid, $prefix)) {
                    return;
                }

                $uid = $this->makeUid($prefix);
                DB::table('students')->where('id', $student->id)->update(['uid' => $uid]);
                $student->uid = $uid;

                // Regenerate QR token so it matches new UID
                $service->generate($student, $school);
            });
        });
    }

    public function down(): void
    {
        // Irreversible — old UIDs were random strings with no inverse mapping
    }

    private function makeUid(string $prefix): string
    {
        do {
            $uid = $prefix . str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        } while (DB::table('students')->where('uid', $uid)->exists());

        return $uid;
    }
};
