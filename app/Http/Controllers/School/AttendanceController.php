<?php

namespace App\Http\Controllers\School;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceController extends BaseSchoolController
{
    private const SESSION_TYPES = ['morning', 'afternoon', 'full_day'];
    private const STATUSES = ['present', 'absent', 'late', 'excused'];

    public function index(Request $request)
    {
        $query = AttendanceSession::with(['schoolClass', 'teacher'])
            ->withCount('records');

        // Teachers only see sessions for classes assigned to them.
        $user = auth()->user();
        if ($user->isTeacher()) {
            $query->whereIn('class_id', $user->assignedClasses()->pluck('classes.id'));
        }

        if ($classId = $request->get('class_id')) {
            $query->where('class_id', $classId);
        }

        if ($date = $request->get('date')) {
            $query->whereDate('session_date', $date);
        }

        $paginator = $query->orderByDesc('session_date')->orderByDesc('id')->paginate(20);
        $classes   = $this->activeClasses();

        return \Inertia\Inertia::render('School/Attendance/Index', [
            'sessions' => [
                'data'      => $paginator->getCollection()->map(fn($s) => [
                    'id'           => $s->id,
                    'session_date' => $s->session_date->format('M d, Y'),
                    'session_type' => $s->session_type,
                    'records_count'=> $s->records_count,
                    'class_name'   => $s->schoolClass?->display_name,
                    'teacher_name' => $s->teacher?->name,
                ])->values(),
                'links'     => $paginator->linkCollection()->toArray(),
                'has_pages' => $paginator->hasPages(),
            ],
            'classes' => $classes->map(fn($c) => ['id' => $c->id, 'display_name' => $c->display_name])->values(),
            'filters' => [
                'class_id' => $request->get('class_id'),
                'date'     => $request->get('date'),
            ],
        ]);
    }

    /**
     * Show the roster for marking attendance. Doubles as the "edit" screen:
     * if a session already exists for the chosen class/date/type, its records
     * pre-fill the form so re-saving updates rather than duplicates.
     */
    public function create(Request $request)
    {
        $this->requireCanRecord();

        $classes = $this->activeClasses();

        $filters = $request->validate([
            'class_id'     => ['nullable', Rule::exists('classes', 'id')->where('school_id', $this->schoolId())],
            'session_date' => ['nullable', 'date'],
            'session_type' => ['nullable', Rule::in(self::SESSION_TYPES)],
        ]);

        $roster = collect();
        $existing = [];
        $class = null;

        if (!empty($filters['class_id'])) {
            $this->requireTeacherCanAccessClass((int) $filters['class_id']);
            $class = SchoolClass::findOrFail($filters['class_id']);

            $roster = Student::where('class_id', $class->id)
                ->where('status', 'active')
                ->orderBy('first_name')
                ->get();

            $session = AttendanceSession::where('class_id', $class->id)
                ->whereDate('session_date', $filters['session_date'] ?? today())
                ->where('session_type', $filters['session_type'] ?? 'morning')
                ->first();

            if ($session) {
                $existing = $session->records()->pluck('status', 'student_id')->all();
            }
        }

        return \Inertia\Inertia::render('School/Attendance/Create', [
            'classes'     => $classes->map(fn($c) => ['id' => $c->id, 'display_name' => $c->display_name])->values(),
            'class'       => $class ? ['id' => $class->id, 'display_name' => $class->display_name] : null,
            'roster'      => $roster->map(fn($s) => [
                'id'         => $s->id,
                'full_name'  => $s->full_name,
                'uid'        => $s->uid,
                'first_name' => $s->first_name,
                'last_name'  => $s->last_name,
            ])->values(),
            'existing'    => $existing,
            'sessionDate' => $filters['session_date'] ?? today()->toDateString(),
            'sessionType' => $filters['session_type'] ?? 'morning',
        ]);
    }

    public function store(Request $request)
    {
        $this->requireCanRecord();
        $this->requireWritableSubscription();

        $validated = $request->validate([
            'class_id'              => ['required', Rule::exists('classes', 'id')->where('school_id', $this->schoolId())],
            'session_date'          => ['required', 'date', 'before_or_equal:today'],
            'session_type'          => ['required', Rule::in(self::SESSION_TYPES)],
            'records'               => ['required', 'array', 'min:1'],
            'records.*.student_id'  => ['required', Rule::exists('students', 'id')->where('school_id', $this->schoolId())],
            'records.*.status'      => ['required', Rule::in(self::STATUSES)],
            'records.*.note'        => ['nullable', 'string', 'max:200'],
        ]);

        $this->requireTeacherCanAccessClass((int) $validated['class_id']);

        // Every record must belong to the session's class — otherwise a crafted
        // payload could write marks for students of another class into this
        // session (same school, but wrong roster = corrupt reports).
        $ids     = collect($validated['records'])->pluck('student_id')->unique();
        $inClass = Student::where('class_id', $validated['class_id'])->whereIn('id', $ids)->count();
        if ($inClass !== $ids->count()) {
            return back()->with('error', 'One or more students are not in the selected class. Refresh the roster and try again.');
        }

        $session = $this->saveAttendance($validated);

        // Absence alerts (Telegram) — best effort AFTER the save committed;
        // an alert failure must never fail the attendance itself.
        try {
            app(\App\Services\AlertService::class)->attendanceSaved($session);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Alert dispatch failed', ['error' => $e->getMessage()]);
        }

        AuditLog::log('attendance.recorded', 'attendance_sessions', $session->id, null, [
            'class_id'     => $session->class_id,
            'session_date' => $session->session_date->toDateString(),
            'session_type' => $session->session_type,
            'records'      => count($validated['records']),
        ]);

        return redirect()
            ->route('school.attendance.show', $session)
            ->with('success', 'Attendance saved.');
    }

    public function show(AttendanceSession $attendance)
    {
        $this->requireTeacherCanAccessClass($attendance->class_id);

        $attendance->load(['schoolClass', 'teacher', 'records.student']);

        return \Inertia\Inertia::render('School/Attendance/Show', [
            'session' => [
                'id'           => $attendance->id,
                'class_name'   => $attendance->schoolClass?->display_name,
                'class_id'     => $attendance->class_id,
                'session_date' => $attendance->session_date->toDateString(),
                'session_type' => $attendance->session_type,
                'teacher_name' => $attendance->teacher?->name,
                'present'      => $attendance->records->where('status', 'present')->count(),
                'late'         => $attendance->records->where('status', 'late')->count(),
                'excused'      => $attendance->records->where('status', 'excused')->count(),
                'absent'       => $attendance->records->where('status', 'absent')->count(),
                'records'      => $attendance->records->sortBy('student.first_name')->map(fn($r) => [
                    'student_name' => $r->student?->full_name,
                    'student_uid'  => $r->student?->uid,
                    'status'       => $r->status,
                    'note'         => $r->note,
                ])->values(),
            ],
        ]);
    }

    /**
     * Persist a session and its records atomically and idempotently.
     *
     * Concurrency design:
     *  - Wrapped in a transaction so a half-written session can never exist.
     *  - The session is resolved with updateOrCreate keyed on the unique slot
     *    (school_id, class_id, session_date, session_type). If two staff submit
     *    the same slot concurrently, the unique index serializes them and both
     *    converge on the same row instead of throwing.
     *  - Records are fully rewritten (delete + bulk insert) inside the same
     *    transaction. This is idempotent — re-saving a roster overwrites prior
     *    marks rather than duplicating them — and it correctly drops records
     *    for students who are no longer on the roster. The roster is a single
     *    class so the row count is small.
     */
    private function saveAttendance(array $data): AttendanceSession
    {
        $date = \Illuminate\Support\Carbon::parse($data['session_date'])->toDateString();

        return DB::transaction(function () use ($data, $date) {
            // Resolve the session for this unique slot. whereDate normalises the
            // comparison (the date cast persists a datetime), so we reliably
            // find an existing session instead of colliding on the unique index.
            $session = AttendanceSession::where('class_id', $data['class_id'])
                ->where('session_type', $data['session_type'])
                ->whereDate('session_date', $date)
                ->first();

            if ($session) {
                $session->update(['teacher_id' => auth()->id(), 'method' => 'manual']);
            } else {
                $session = AttendanceSession::create([
                    'class_id'     => $data['class_id'],
                    'session_date' => $date,
                    'session_type' => $data['session_type'],
                    'teacher_id'   => auth()->id(),
                    'method'       => 'manual',
                ]);
            }

            $session->records()->delete();

            $now = now();
            $rows = [];
            foreach ($data['records'] as $record) {
                $rows[] = [
                    'session_id' => $session->id,
                    'student_id' => $record['student_id'],
                    'status'     => $record['status'],
                    'note'       => $record['note'] ?? null,
                    'scanned_at' => null,
                    'created_at' => $now,
                ];
            }

            AttendanceRecord::insert($rows);

            return $session;
        });
    }

    /**
     * Active classes for dropdowns — scoped to the current school AND, for
     * teachers, to their assigned classes only (mirrors the mobile API's
     * BaseApiController scoping; the web side must never be looser).
     */
    private function activeClasses()
    {
        return $this->classesForCurrentUser();
    }

    /**
     * Attendance may be recorded by school admins and teachers. Pure support
     * staff cannot record student attendance.
     */
    private function requireCanRecord(): void
    {
        $user = auth()->user();
        if (! $user->isSchoolAdmin() && ! $user->isTeacher()) {
            abort(403, 'Only administrators and teachers can record attendance.');
        }
    }
}
