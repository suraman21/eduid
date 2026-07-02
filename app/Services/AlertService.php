<?php

namespace App\Services;

use App\Models\AlertOutbox;
use App\Models\AttendanceSession;
use App\Models\Guardian;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Absence alerts ('alerts' module). Called after an attendance session is
 * saved (web form or mobile sync): enqueues one Telegram message per
 * (absent student × linked guardian) into the outbox, then flushes a small
 * batch inline — best effort, the cron command retries the rest.
 */
class AlertService
{
    /** Max messages sent inline within a web/sync request. */
    private const INLINE_FLUSH = 15;

    public function __construct(private TelegramService $telegram)
    {
    }

    /**
     * Enqueue alerts for the session's absent students. Safe to call on
     * every (re-)save: the outbox unique key makes it idempotent.
     */
    public function attendanceSaved(AttendanceSession $session): void
    {
        $school = $session->school;

        if (! $school || ! $school->hasFeature('alerts')) {
            return;
        }

        $absent = $session->records()
            ->where('status', 'absent')
            ->with('student:id,first_name,last_name,uid')
            ->get();

        if ($absent->isEmpty()) {
            $this->flush($school->id, self::INLINE_FLUSH); // still retry stragglers
            return;
        }

        // Linked guardians of the absent students (has a Telegram chat).
        $guardianLinks = DB::table('student_guardians')
            ->join('guardians', 'guardians.id', '=', 'student_guardians.guardian_id')
            ->whereIn('student_guardians.student_id', $absent->pluck('student_id'))
            ->whereNotNull('guardians.telegram_chat_id')
            ->get(['student_guardians.student_id', 'guardians.id as guardian_id']);

        $date = $session->session_date instanceof \DateTimeInterface
            ? $session->session_date->format('d M Y')
            : (string) $session->session_date;

        foreach ($absent as $record) {
            $student = $record->student;
            if (! $student) {
                continue;
            }

            $message = "❗ {$student->full_name} was marked ABSENT"
                . " ({$session->session_type}, {$date}) at {$school->name}."
                . ' If this is unexpected, please contact the school.';

            foreach ($guardianLinks->where('student_id', $record->student_id) as $link) {
                // insertOrIgnore + unique key = exactly one alert per
                // session × student × guardian, ever.
                DB::table('alert_outbox')->insertOrIgnore([
                    'school_id'   => $school->id,
                    'guardian_id' => $link->guardian_id,
                    'student_id'  => $record->student_id,
                    'session_id'  => $session->id,
                    'channel'     => 'telegram',
                    'message'     => $message,
                    'status'      => 'pending',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }

        $this->flush($school->id, self::INLINE_FLUSH);
    }

    /**
     * Send pending outbox rows (oldest first). Rows failing 5 times park as
     * 'failed'. Returns [sent, failed] counts.
     *
     * @return array{0: int, 1: int}
     */
    public function flush(?int $schoolId = null, int $limit = 100): array
    {
        if (! $this->telegram->isConfigured()) {
            return [0, 0]; // rows wait until the bot token exists
        }

        $rows = AlertOutbox::withoutGlobalScope('school')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $chatId = Guardian::withoutGlobalScope('school')
                ->whereKey($row->guardian_id)->value('telegram_chat_id');

            if (! $chatId) {
                $row->update(['status' => 'failed', 'last_error' => 'Guardian unlinked.']);
                $failed++;
                continue;
            }

            $error = $this->telegram->send($chatId, $row->message);

            if ($error === null) {
                $row->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
                $sent++;
            } else {
                $attempts = $row->attempts + 1;
                $row->update([
                    'attempts'   => $attempts,
                    'status'     => $attempts >= 5 ? 'failed' : 'pending',
                    'last_error' => mb_substr($error, 0, 500),
                ]);
                $failed++;
                Log::warning('Telegram alert failed', ['outbox_id' => $row->id, 'error' => $error]);
            }
        }

        return [$sent, $failed];
    }
}
