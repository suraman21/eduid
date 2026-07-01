<?php

namespace App\Http\Controllers\School;

use App\Models\AuditLog;
use App\Models\PhotoSession;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class PhotoSessionController extends BaseSchoolController
{
    public function index()
    {
        $sessions = PhotoSession::with(['createdBy', 'schoolClass'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return \Inertia\Inertia::render('School/PhotoSessions/Index', [
            'sessions' => [
                'data'      => $sessions->getCollection()->map(fn($s) => [
                    'id'               => $s->id,
                    'session_type'     => $s->session_type,
                    'class_name'       => $s->schoolClass?->display_name,
                    'status'           => $s->status,
                    'photos_captured'  => $s->photos_captured,
                    'total_students'   => $s->total_students,
                    'progress'         => $s->progress,
                    'created_at_human' => $s->created_at->diffForHumans(),
                    'is_active'        => $s->isActive(),
                ])->values(),
                'total'     => $sessions->total(),
                'links'     => $sessions->linkCollection()->toArray(),
                'has_pages' => $sessions->hasPages(),
            ],
        ]);
    }

    public function create()
    {
        $this->requireAdmin();

        return \Inertia\Inertia::render('School/PhotoSessions/Create', [
            'classes' => SchoolClass::where('is_active', true)->orderBy('grade')->get()
                ->map(fn($c) => ['id' => $c->id, 'display_name' => $c->display_name])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $validated = $request->validate([
            'class_id'     => 'nullable|exists:classes,id',
            'session_type' => 'required|in:app_capture,zip_upload',
        ]);

        // Build alphabetical student queue
        $query = Student::where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name');

        if ($validated['class_id'] ?? null) {
            $query->where('class_id', $validated['class_id']);
        }

        $students = $query->get();

        if ($students->isEmpty()) {
            return back()->with('error', 'No active students found for the selected class.')->withInput();
        }

        // Cancel any other active sessions for this school
        PhotoSession::where('status', 'active')->update(['status' => 'cancelled', 'completed_at' => now()]);

        $session = PhotoSession::create([
            'school_id'       => $this->schoolId(),
            'created_by'      => auth()->id(),
            'class_id'        => $validated['class_id'] ?? null,
            'session_type'    => $validated['session_type'],
            'status'          => 'active',
            'student_queue'   => $students->pluck('id')->all(),
            'total_students'  => $students->count(),
            'photos_captured' => 0,
            'current_position' => 0,
        ]);

        AuditLog::log('photo_session.created', 'photo_sessions', $session->id, null, [
            'session_type'   => $session->session_type,
            'total_students' => $session->total_students,
        ]);

        return redirect()->route('school.photo-sessions.show', $session);
    }

    public function show(PhotoSession $photoSession)
    {
        $photoSession->load(['createdBy', 'schoolClass']);
        $currentStudent = $photoSession->getCurrentStudent();

        // All students in queue with their current photo status
        $queueStudents = Student::whereIn('id', $photoSession->student_queue)
            ->get()
            ->keyBy('id');

        return \Inertia\Inertia::render('School/PhotoSessions/Show', [
            'session' => [
                'id'               => $photoSession->id,
                'session_type'     => $photoSession->session_type,
                'status'           => $photoSession->status,
                'class_name'       => $photoSession->schoolClass?->display_name,
                'photos_captured'  => $photoSession->photos_captured,
                'total_students'   => $photoSession->total_students,
                'progress'         => $photoSession->progress,
                'current_position' => $photoSession->current_position,
                'student_queue'    => $photoSession->student_queue,
                'is_active'        => $photoSession->isActive(),
                'created_by_name'  => $photoSession->createdBy?->name,
            ],
            'currentStudent' => $currentStudent ? [
                'id'         => $currentStudent->id,
                'full_name'  => $currentStudent->full_name,
                'uid'        => $currentStudent->uid,
                'class_name' => $currentStudent->schoolClass?->display_name,
                'photo_url'  => $currentStudent->photo_url,
            ] : null,
            'queueStudents' => $queueStudents->map(fn($s) => [
                'id'        => $s->id,
                'full_name' => $s->full_name,
                'uid'       => $s->uid,
                'photo_url' => $s->photo_url,
            ])->toArray(),
        ]);
    }

    /**
     * Upload a photo for the current student in queue and advance.
     */
    public function uploadPhoto(Request $request, PhotoSession $photoSession)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        // The position/captured counters are advanced under a row lock: two
        // concurrent uploads (or an upload racing a skip) would otherwise both
        // read the same position and only advance one slot (TOCTOU), skipping a
        // student and corrupting the completion state.
        $result = DB::transaction(function () use ($request, $photoSession) {
            $session = PhotoSession::whereKey($photoSession->id)->lockForUpdate()->firstOrFail();

            if (! $session->isActive()) {
                return ['error' => 'This session is no longer active.'];
            }

            $student = $session->getCurrentStudent();
            if (! $student) {
                return ['error' => 'All students have been photographed.'];
            }

            $path = $request->file('photo')->store(
                "schools/{$this->schoolId()}/students",
                'local'
            );

            // Delete old photo if present (either disk: legacy public or new private)
            if ($student->photo_path) {
                Storage::disk('local')->delete($student->photo_path);
                Storage::disk('public')->delete($student->photo_path);
            }

            $student->update(['photo_path' => $path]);

            $newPosition = $session->current_position + 1;
            $isComplete  = $newPosition >= $session->total_students;

            $session->update([
                'current_position' => $newPosition,
                'photos_captured'  => $session->photos_captured + 1,
                'status'           => $isComplete ? 'completed' : 'active',
                'completed_at'     => $isComplete ? now() : null,
            ]);

            return ['student' => $student, 'complete' => $isComplete];
        });

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        if ($result['complete']) {
            return redirect()->route('school.photo-sessions.show', $photoSession)
                ->with('success', 'All photos captured. Session complete!');
        }

        return redirect()->route('school.photo-sessions.show', $photoSession)
            ->with('success', "Photo saved for {$result['student']->full_name}.");
    }

    /**
     * Skip the current student (no photo yet).
     */
    public function skipStudent(PhotoSession $photoSession)
    {
        $this->requireAdmin();

        // Same row lock as uploadPhoto — a skip racing an upload must not lose
        // or double-advance the queue position.
        $error = DB::transaction(function () use ($photoSession) {
            $session = PhotoSession::whereKey($photoSession->id)->lockForUpdate()->firstOrFail();

            if (! $session->isActive()) {
                return 'This session is no longer active.';
            }

            $newPosition = $session->current_position + 1;
            $isComplete  = $newPosition >= $session->total_students;

            $session->update([
                'current_position' => $newPosition,
                'status'           => $isComplete ? 'completed' : 'active',
                'completed_at'     => $isComplete ? now() : null,
            ]);

            return null;
        });

        if ($error) {
            return back()->with('error', $error);
        }

        return redirect()->route('school.photo-sessions.show', $photoSession)
            ->with('info', 'Student skipped.');
    }

    /**
     * Handle ZIP upload for zip_upload sessions.
     * Files named 001.jpg, 002.jpg... are matched to the queue by position.
     */
    public function uploadZip(Request $request, PhotoSession $photoSession)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        if (! $photoSession->isActive()) {
            return back()->with('error', 'This session is no longer active.');
        }

        $request->validate([
            'zip_file' => 'required|file|mimes:zip|max:102400',
        ]);

        $zip = new ZipArchive();
        $tmpPath = $request->file('zip_file')->getRealPath();

        if ($zip->open($tmpPath) !== true) {
            return back()->with('error', 'Could not open ZIP file. Please try again.');
        }

        $queue   = $photoSession->student_queue;
        $matched = 0;
        $skipped = [];

        // Collect and sort image entries by numeric prefix
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('/^(\d+)\.(jpe?g|png|webp)$/i', basename($name), $m)) {
                $entries[intval($m[1])] = ['name' => $name, 'index' => $i];
            }
        }
        ksort($entries);

        $position = 0;
        foreach ($entries as $seq => $entry) {
            $studentId = $queue[$position] ?? null;
            if (! $studentId) {
                break;
            }

            $student = Student::find($studentId);
            if (! $student) {
                $position++;
                continue;
            }

            $contents = $zip->getFromIndex($entry['index']);
            if ($contents === false) {
                $skipped[] = $position + 1;
                $position++;
                continue;
            }

            $ext  = strtolower(pathinfo($entry['name'], PATHINFO_EXTENSION));
            $dest = "schools/{$this->schoolId()}/students/{$student->uid}.{$ext}";
            Storage::disk('local')->put($dest, $contents);

            if ($student->photo_path) {
                Storage::disk('local')->delete($student->photo_path);
                Storage::disk('public')->delete($student->photo_path);
            }

            $student->update(['photo_path' => $dest]);
            $matched++;
            $position++;
        }

        $zip->close();

        $photoSession->update([
            'photos_captured'  => $photoSession->photos_captured + $matched,
            'current_position' => min($position, $photoSession->total_students),
        ]);

        $msg = "{$matched} photo(s) matched and saved.";
        if ($skipped) {
            $msg .= ' ' . count($skipped) . ' file(s) could not be read.';
        }

        AuditLog::log('photo_session.zip_uploaded', 'photo_sessions', $photoSession->id, null, [
            'matched' => $matched,
        ]);

        return redirect()->route('school.photo-sessions.show', $photoSession)->with('success', $msg);
    }

    public function complete(PhotoSession $photoSession)
    {
        $this->requireAdmin();

        $photoSession->update(['status' => 'completed', 'completed_at' => now()]);

        AuditLog::log('photo_session.completed', 'photo_sessions', $photoSession->id, null, []);

        return redirect()->route('school.photo-sessions.index')
            ->with('success', 'Photo session marked as complete.');
    }

    public function cancel(PhotoSession $photoSession)
    {
        $this->requireAdmin();

        $photoSession->update(['status' => 'cancelled', 'completed_at' => now()]);

        return redirect()->route('school.photo-sessions.index')
            ->with('success', 'Photo session cancelled.');
    }
}
