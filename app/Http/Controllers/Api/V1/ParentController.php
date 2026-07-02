<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Http\Request;

/**
 * Parent app API — a guardian's window onto THEIR children only.
 *
 * Access model (defense in depth on every endpoint):
 *   1. sanctum token + role:parent (route middleware)
 *   2. the school's package must include the 'parent_app' module
 *   3. the requested student must be linked to the caller's guardian
 *      record via student_guardians — anything else 404s
 *
 * Read-only by design; grades expose PUBLISHED rows only (drafts are the
 * teacher's workspace, never parent-visible).
 */
class ParentController extends Controller
{
    /** The caller's guardian record, or abort. */
    private function guardian(Request $request): Guardian
    {
        $guardian = Guardian::withoutGlobalScope('school')
            ->where('school_id', $request->user()->school_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $guardian) {
            abort(403, 'No guardian record is linked to this account.');
        }

        return $guardian;
    }

    /** Abort 404 unless the student is one of the caller's children. */
    private function childOrFail(Request $request, int $studentId): Student
    {
        $student = $this->guardian($request)->students()
            ->where('students.id', $studentId)
            ->first();

        if (! $student) {
            abort(404, 'Student not found.');
        }

        return $student;
    }

    public function children(Request $request)
    {
        $children = $this->guardian($request)->students()
            ->with('schoolClass:id,grade,section')
            ->get();

        return response()->json([
            'children' => $children->map(fn ($s) => [
                'id'         => $s->id,
                'uid'        => $s->uid,
                'full_name'  => $s->full_name,
                'gender'     => $s->gender,
                'status'     => $s->status,
                'class_name' => $s->schoolClass
                    ? "{$s->schoolClass->grade} - {$s->schoolClass->section}"
                    : null,
                'is_primary' => (bool) $s->pivot->is_primary,
            ])->values(),
        ]);
    }

    /** Attendance: current-month summary + the 30 most recent records. */
    public function attendance(Request $request, int $student)
    {
        $child = $this->childOrFail($request, $student);

        $records = AttendanceRecord::query()
            ->where('student_id', $child->id)
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.session_id')
            ->orderByDesc('attendance_sessions.session_date')
            ->limit(30)
            ->get([
                'attendance_records.status',
                'attendance_records.note',
                'attendance_sessions.session_date',
                'attendance_sessions.session_type',
            ]);

        $monthCounts = AttendanceRecord::query()
            ->where('student_id', $child->id)
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.session_id')
            ->whereBetween('attendance_sessions.session_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('attendance_records.status, COUNT(*) as c')
            ->groupBy('attendance_records.status')
            ->pluck('c', 'status');

        return response()->json([
            'student' => ['id' => $child->id, 'full_name' => $child->full_name],
            'month_summary' => [
                'present' => (int) ($monthCounts['present'] ?? 0),
                'absent'  => (int) ($monthCounts['absent'] ?? 0),
                'late'    => (int) ($monthCounts['late'] ?? 0),
                'excused' => (int) ($monthCounts['excused'] ?? 0),
            ],
            'records' => $records->map(fn ($r) => [
                'date'   => substr((string) $r->session_date, 0, 10),
                'type'   => $r->session_type,
                'status' => $r->status,
                'note'   => $r->note,
            ])->values(),
        ]);
    }

    /** PUBLISHED grades only, grouped term → subject → assessments. */
    public function grades(Request $request, int $student)
    {
        $child = $this->childOrFail($request, $student);

        $grades = StudentGrade::query()
            ->where('student_id', $child->id)
            ->where('status', 'published')
            ->get(['subject_id', 'term_id', 'assessment_type', 'score', 'max_score']);

        $subjects = Subject::withoutGlobalScope('school')
            ->whereIn('id', $grades->pluck('subject_id')->unique())
            ->pluck('name', 'id');
        $terms = Term::withoutGlobalScope('school')
            ->whereIn('id', $grades->pluck('term_id')->unique())
            ->get(['id', 'name', 'academic_year'])->keyBy('id');

        $byTerm = $grades->groupBy('term_id')->map(function ($termGrades, $termId) use ($subjects, $terms) {
            $term = $terms->get($termId);

            return [
                'term'          => $term?->name ?? 'Term',
                'academic_year' => $term?->academic_year,
                'subjects'      => $termGrades->groupBy('subject_id')
                    ->map(function ($rows, $subjectId) use ($subjects) {
                        $score = (float) $rows->sum('score');
                        $max   = (float) $rows->sum('max_score');

                        return [
                            'subject'     => $subjects[$subjectId] ?? 'Subject',
                            'assessments' => $rows->map(fn ($g) => [
                                'type'      => $g->assessment_type,
                                'score'     => (float) $g->score,
                                'max_score' => (float) $g->max_score,
                            ])->values(),
                            'total_pct' => $max > 0 ? round($score / $max * 100, 1) : null,
                        ];
                    })->values(),
            ];
        })->values();

        return response()->json([
            'student' => ['id' => $child->id, 'full_name' => $child->full_name],
            'terms'   => $byTerm,
        ]);
    }
}
