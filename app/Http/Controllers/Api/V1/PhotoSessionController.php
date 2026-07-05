<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\PhotoSession;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Mobile Photo Session Mode (school-admin ring at the route level).
 *
 * The admin creates a session on the web (whole school or one class); the
 * app picks it up here and walks the queue student-by-student with the
 * camera: framing guide on screen, client-side compression, offline queueing
 * in the app, then upload here when connectivity returns.
 *
 * The queue advance runs under the same row lock as the web uploader, so a
 * phone and the web page can work the same session without double-advancing.
 */
class PhotoSessionController extends BaseApiController
{
    /** The school's active session (or null) + where the queue stands. */
    public function active()
    {
        $session = PhotoSession::with('schoolClass:id,grade,section')
            ->where('status', 'active')
            ->latest()
            ->first();

        if (! $session) {
            return response()->json(['session' => null]);
        }

        return response()->json(['session' => $this->payload($session)]);
    }

    /**
     * Upload a photo for a SPECIFIC student in the session queue.
     *
     * The app sends the student_id it captured for (not "whoever is current")
     * because offline capture means uploads can arrive out of order. Position
     * advances only when the CURRENT student's photo lands; photos for other
     * queued students are stored without disturbing the pointer.
     */
    public function upload(Request $request, PhotoSession $photoSession)
    {
        $validated = $request->validate([
            'photo'      => 'required|image|max:8192',
            'student_id' => 'required|integer',
        ]);

        // Student must belong to this school AND this session's queue.
        $student = Student::find($validated['student_id']);
        if (! $student || ! in_array($student->id, $photoSession->student_queue ?? [], true)) {
            return response()->json(['message' => 'That student is not part of this photo session.'], 422);
        }

        if (! $photoSession->isActive()) {
            return response()->json(['message' => 'This photo session is no longer active.'], 409);
        }

        $path = $request->file('photo')->store("schools/{$student->school_id}/students", 'local');

        $fresh = DB::transaction(function () use ($photoSession, $student, $path) {
            $session = PhotoSession::whereKey($photoSession->id)->lockForUpdate()->firstOrFail();

            if ($student->photo_path) {
                Storage::disk('local')->delete($student->photo_path);
                Storage::disk('public')->delete($student->photo_path);
            }
            $student->update(['photo_path' => $path]);

            // Advance the pointer only when this was the current student.
            $current = $session->getCurrentStudent();
            $updates = ['photos_captured' => $session->photos_captured + 1];
            if ($current && $current->id === $student->id) {
                $newPos = $session->current_position + 1;
                $updates['current_position'] = $newPos;
                if ($newPos >= $session->total_students) {
                    $updates['status'] = 'completed';
                    $updates['completed_at'] = now();
                }
            }
            $session->update($updates);

            return $session->fresh();
        });

        AuditLog::safeRecord($student->school_id, $this->user()->id,
            'photo_session.mobile_upload', 'photo_sessions', $photoSession->id, null,
            ['student_id' => $student->id]);

        return response()->json([
            'message' => "Photo saved for {$student->full_name}.",
            'session' => $this->payload($fresh),
        ]);
    }

    /** Skip the current student (same lock as the web flow). */
    public function skip(PhotoSession $photoSession)
    {
        $result = DB::transaction(function () use ($photoSession) {
            $session = PhotoSession::whereKey($photoSession->id)->lockForUpdate()->firstOrFail();

            if (! $session->isActive()) {
                return null;
            }

            $newPos = $session->current_position + 1;
            $done   = $newPos >= $session->total_students;
            $session->update([
                'current_position' => $newPos,
                'status'           => $done ? 'completed' : 'active',
                'completed_at'     => $done ? now() : null,
            ]);

            return $session->fresh();
        });

        if (! $result) {
            return response()->json(['message' => 'This photo session is no longer active.'], 409);
        }

        return response()->json(['message' => 'Student skipped.', 'session' => $this->payload($result)]);
    }

    /** Session + full queue with per-student photo state for the app UI. */
    private function payload(PhotoSession $session): array
    {
        $students = Student::whereIn('id', $session->student_queue ?? [])
            ->get(['id', 'first_name', 'last_name', 'uid', 'photo_path'])
            ->keyBy('id');

        $queue = collect($session->student_queue ?? [])->map(function ($id, $i) use ($students, $session) {
            $s = $students->get($id);
            return $s ? [
                'id'        => $s->id,
                'full_name' => $s->full_name,
                'uid'       => $s->uid,
                'has_photo' => ! empty($s->photo_path),
                'position'  => $i,
                'is_current' => $i === (int) $session->current_position,
            ] : null;
        })->filter()->values();

        return [
            'id'               => $session->id,
            'class_name'       => $session->schoolClass?->display_name,
            'status'           => $session->status,
            'total_students'   => $session->total_students,
            'photos_captured'  => $session->photos_captured,
            'current_position' => $session->current_position,
            'queue'            => $queue,
        ];
    }
}
