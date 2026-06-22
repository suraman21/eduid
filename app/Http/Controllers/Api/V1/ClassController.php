<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\SchoolClass;
use App\Models\Student;

class ClassController extends BaseApiController
{
    /**
     * Classes the teacher can take attendance for. The mobile app caches these
     * for the offline class picker.
     */
    public function index()
    {
        $classes = $this->classesForUser()->map(fn (SchoolClass $c) => [
            'id'            => $c->id,
            'name'          => $c->display_name,
            'grade'         => $c->grade,
            'section'       => $c->section,
            'student_count' => $c->students()->where('status', 'active')->count(),
        ]);

        return response()->json(['classes' => $classes], 200);
    }

    /**
     * Active roster for one class — cached by the app so QR verification and
     * the manual fallback work fully offline. Includes each student's qr_token
     * so the scanner can validate locally without a round-trip.
     */
    public function students(SchoolClass $class)
    {
        if (! $this->canAccessClass($class->id)) {
            return response()->json(['message' => 'You are not assigned to this class.'], 403);
        }

        $students = Student::where('class_id', $class->id)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Student $s) => [
                'id'         => $s->id,
                'uid'        => $s->uid,
                'first_name' => $s->first_name,
                'last_name'  => $s->last_name,
                'full_name'  => $s->full_name,
                'gender'     => $s->gender,
                'status'     => $s->status,
                'has_photo'  => ! empty($s->photo_path),
                'qr_token'   => $s->qr_token,
            ]);

        return response()->json([
            'class' => [
                'id'      => $class->id,
                'name'    => $class->display_name,
                'grade'   => $class->grade,
                'section' => $class->section,
            ],
            'academic_year' => $this->currentSchool()->academic_year,
            'students'      => $students,
        ], 200);
    }
}
