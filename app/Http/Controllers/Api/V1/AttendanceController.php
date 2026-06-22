<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\Student;
use App\Services\QrTokenService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceController extends BaseApiController
{
    private const SESSION_TYPES = ['morning', 'afternoon', 'full_day'];
    private const STATUSES       = ['present', 'absent', 'late', 'excused'];

    /**
     * Online QR verification. The app sends the raw scanned payload; we confirm
     * the student is real, active and enrolled for the current year. Offline,
     * the app does the same HMAC check locally against the cached roster.
     */
    public function verifyQr(Request $request, QrTokenService $qr)
    {
        $validated = $request->validate([
            'qr_data' => ['required', 'string', 'max:300'],
        ]);

        $student = $qr->verifyPayloadForSchool($validated['qr_data'], $this->currentSchool());

        if (! $student) {
            return response()->json([
                'valid'   => false,
                'message' => 'Invalid or expired QR code.',
            ], 200);
        }

        if ($student->status !== 'active') {
            return response()->json([
                'valid'   => false,
                'message' => "This student is not active ({$student->status}).",
                'student' => $this->studentSummary($student),
            ], 200);
        }

        return response()->json([
            'valid'   => true,
            'student' => $this->studentSummary($student),
        ], 200);
    }

    /**
     * Bulk sync of offline-captured attendance sessions.
     *
     * Each session is processed independently and idempotently:
     *  - idempotency_key (unique) makes re-sending the same batch a no-op
     *  - the unique slot (school, class, date, type) is resolved with
     *    updateOrCreate semantics so an already-existing session is merged into
     *    rather than colliding on the index
     *  - records are upserted per student
     *
     * The response reports per-session results so the app can mark each as
     * synced (or surface a per-session error) without failing the whole batch.
     */
    public function sync(Request $request)
    {
        $validated = $request->validate([
            'sessions'                       => ['required', 'array', 'min:1', 'max:100'],
            'sessions.*.class_id'            => ['required', 'integer'],
            'sessions.*.session_date'        => ['required', 'date', 'before_or_equal:today'],
            'sessions.*.session_type'        => ['required', Rule::in(self::SESSION_TYPES)],
            'sessions.*.device_id'           => ['nullable', 'string', 'max:100'],
            'sessions.*.idempotency_key'     => ['required', 'string', 'max:100'],
            'sessions.*.records'             => ['required', 'array', 'min:1'],
            'sessions.*.records.*.student_id'=> ['required', 'integer'],
            'sessions.*.records.*.status'    => ['required', Rule::in(self::STATUSES)],
            'sessions.*.records.*.scanned_at'=> ['nullable', 'date'],
            'sessions.*.records.*.note'      => ['nullable', 'string', 'max:200'],
        ]);

        $results = [];
        foreach ($validated['sessions'] as $session) {
            $results[] = $this->syncOneSession($session);
        }

        return response()->json([
            'synced'  => collect($results)->whereIn('status', ['created', 'merged'])->count(),
            'skipped' => collect($results)->where('status', 'duplicate')->count(),
            'failed'  => collect($results)->where('status', 'error')->count(),
            'results' => $results,
        ], 200);
    }

    /**
     * Process a single session payload. Never throws — returns a result row.
     */
    private function syncOneSession(array $data): array
    {
        $key = $data['idempotency_key'];

        // Authorisation: the class must belong to the school and (for teachers)
        // be one they are assigned to.
        if (! $this->canAccessClass($data['class_id'])) {
            return $this->result($key, 'error', 'Not authorised for this class.');
        }

        // Fast idempotency check — a re-sent batch resolves here without writes.
        $existing = AttendanceSession::where('idempotency_key', $key)->first();
        if ($existing) {
            return $this->result($key, 'duplicate', 'Already synced.', $existing->id);
        }

        // All records must reference students in this school (global scope) and
        // ideally in this class. Reject the whole session if any student is bad.
        $studentIds  = collect($data['records'])->pluck('student_id')->unique();
        $validIds    = Student::whereIn('id', $studentIds)
            ->where('class_id', $data['class_id'])
            ->pluck('id');

        if ($validIds->count() !== $studentIds->count()) {
            return $this->result($key, 'error', 'One or more students do not belong to this class.');
        }

        $date = Carbon::parse($data['session_date'])->toDateString();

        try {
            return DB::transaction(function () use ($data, $key, $date) {
                // Resolve the unique slot. If a session already exists (e.g.
                // recorded on the web), merge into it; otherwise create.
                $session = AttendanceSession::where('class_id', $data['class_id'])
                    ->where('session_type', $data['session_type'])
                    ->whereDate('session_date', $date)
                    ->first();

                $merged = (bool) $session;

                if ($session) {
                    $session->update([
                        'synced_from_app' => true,
                        'device_id'       => $data['device_id'] ?? $session->device_id,
                        'method'          => 'qr_scan',
                    ]);
                } else {
                    $session = AttendanceSession::create([
                        'class_id'        => $data['class_id'],
                        'session_date'    => $date,
                        'session_type'    => $data['session_type'],
                        'teacher_id'      => $this->user()->id,
                        'method'          => 'qr_scan',
                        'synced_from_app' => true,
                        'device_id'       => $data['device_id'] ?? null,
                        'idempotency_key' => $key,
                    ]);
                }

                foreach ($data['records'] as $record) {
                    AttendanceRecord::updateOrCreate(
                        ['session_id' => $session->id, 'student_id' => $record['student_id']],
                        [
                            'status'     => $record['status'],
                            'note'       => $record['note'] ?? null,
                            'scanned_at' => isset($record['scanned_at'])
                                ? Carbon::parse($record['scanned_at'])
                                : null,
                        ]
                    );
                }

                AuditLog::log('attendance.synced', 'attendance_sessions', $session->id, null, [
                    'class_id'        => $session->class_id,
                    'session_date'    => $date,
                    'session_type'    => $session->session_type,
                    'records'         => count($data['records']),
                    'idempotency_key' => $key,
                ]);

                return $this->result(
                    $key,
                    $merged ? 'merged' : 'created',
                    null,
                    $session->id,
                    count($data['records'])
                );
            });
        } catch (QueryException $e) {
            // A concurrent request inserted the same idempotency_key between our
            // check and write — that's still a successful (duplicate) sync.
            if ($this->isDuplicateKey($e)) {
                $dupe = AttendanceSession::where('idempotency_key', $key)->first();
                return $this->result($key, 'duplicate', 'Already synced.', $dupe?->id);
            }
            throw $e;
        }
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return $e->getCode() === '23000'
            && str_contains($e->getMessage(), 'idempotency_key');
    }

    private function result(string $key, string $status, ?string $message = null, ?int $sessionId = null, int $records = 0): array
    {
        return array_filter([
            'idempotency_key' => $key,
            'status'          => $status,
            'session_id'      => $sessionId,
            'records_saved'   => $records ?: null,
            'message'         => $message,
        ], fn ($v) => $v !== null);
    }

    private function studentSummary(Student $s): array
    {
        return [
            'id'         => $s->id,
            'uid'        => $s->uid,
            'first_name' => $s->first_name,
            'last_name'  => $s->last_name,
            'full_name'  => $s->full_name,
            'gender'     => $s->gender,
            'status'     => $s->status,
            'class_id'   => $s->class_id,
            'class_name' => $s->schoolClass?->display_name,
            'has_photo'  => ! empty($s->photo_path),
        ];
    }
}
