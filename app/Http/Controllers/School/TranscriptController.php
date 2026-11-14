<?php

namespace App\Http\Controllers\School;

use App\Models\LifecycleEvent;
use App\Models\Student;
use App\Models\Transcript;
use App\Services\TranscriptService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

use Inertia\Inertia;

/**
 * Academic transcripts: preview the live record, then issue a frozen copy.
 *
 * Preview always reflects current published marks. Issuing stores a snapshot
 * so a document handed to a student can never silently change afterwards.
 */
class TranscriptController extends BaseSchoolController
{
    public function __construct(private TranscriptService $transcripts)
    {
    }

    /** Live preview — always the current truth. */
    public function show(Student $student)
    {
        $school = $this->currentSchool();

        return Inertia::render('School/Transcripts/Show', [
            'transcript'  => $this->transcripts->build($student),
            'schoolName'  => $school->name,
            'issued'      => Transcript::query()
                ->where('student_id', $student->id)
                ->orderByDesc('issued_on')
                ->get()
                ->map(fn ($t) => [
                    'id'           => $t->id,
                    'reference'    => $t->reference,
                    'purpose'      => $t->purpose,
                    'purpose_label'=> $t->purposeLabel(),
                    'issued_on'    => $t->issued_on?->format('Y-m-d'),
                    'issued_by'    => $t->issuer?->name,
                    'is_revoked'   => $t->isRevoked(),
                ]),
            'purposes'   => collect(Transcript::PURPOSES)
                ->map(fn ($label, $key) => ['value' => $key, 'label' => $label])
                ->values(),
            'next_reference' => $this->nextReference($student),
            'is_admin'   => auth()->user()?->isSchoolAdmin() ?? false,
        ]);
    }

    /** Freeze the current record into an issued transcript. */
    public function issue(Request $request, Student $student)
    {
        $this->requireAdmin();

        $data = $request->validate([
            'purpose'   => ['required', Rule::in(array_keys(Transcript::PURPOSES))],
            'note'      => ['nullable', 'string', 'max:500'],
            'issued_on' => ['nullable', 'date'],
        ]);

        $snapshot = $this->transcripts->build($student);

        $transcript = DB::transaction(function () use ($student, $data, $snapshot) {
            return Transcript::create([
                'school_id'  => $student->school_id,
                'student_id' => $student->id,
                'reference'  => $this->nextReference($student),
                'purpose'    => $data['purpose'],
                'issued_on'  => $data['issued_on'] ?? now()->toDateString(),
                'snapshot'   => $snapshot,
                'note'       => $data['note'] ?? null,
                'issued_by'  => auth()->id(),
            ]);
        });

        // A transcript issued for a transfer is a lifecycle fact worth keeping
        // on the student's own timeline.
        LifecycleEvent::create([
            'school_id'   => $student->school_id,
            'student_id'  => $student->id,
            'event'       => 'document_added',
            'occurred_on' => $transcript->issued_on,
            'notes'       => "Transcript {$transcript->reference} issued ({$transcript->purposeLabel()}).",
            'recorded_by' => auth()->id(),
        ]);

        return redirect()
            ->route('school.students.transcripts.issued', [$student->id, $transcript->id])
            ->with('success', "Transcript {$transcript->reference} issued.");
    }

    /** View an issued (frozen) transcript. */
    public function issued(Student $student, Transcript $transcript)
    {
        abort_unless($transcript->student_id === $student->id, 404);

        $transcript->load('issuer:id,name');

        return Inertia::render('School/Transcripts/Issued', [
            'transcript' => [
                'id'            => $transcript->id,
                'reference'     => $transcript->reference,
                'purpose'       => $transcript->purpose,
                'purpose_label' => $transcript->purposeLabel(),
                'issued_on'     => $transcript->issued_on?->format('Y-m-d'),
                'issued_by'     => $transcript->issuer?->name,
                'note'          => $transcript->note,
                'is_revoked'    => $transcript->isRevoked(),
                'revoked_reason'=> $transcript->revoked_reason,
                'snapshot'      => $transcript->snapshot,
            ],
            'schoolName' => $this->currentSchool()->name,
            'student'    => [
                'id'   => $student->id,
                'name' => $student->full_name,
                'uid'  => $student->uid,
            ],
            'is_admin' => auth()->user()?->isSchoolAdmin() ?? false,
        ]);
    }

    /** Revoke a transcript issued in error. The record is kept. */
    public function revoke(Request $request, Student $student, Transcript $transcript)
    {
        $this->requireAdmin();
        abort_unless($transcript->student_id === $student->id, 404);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $transcript->update([
            'revoked_at'     => now(),
            'revoked_reason' => $data['reason'],
        ]);

        return back()->with('success', "Transcript {$transcript->reference} revoked.");
    }

    /** "TR-AIA-0001" — per school, never reused. */
    private function nextReference(Student $student): string
    {
        $school = $this->currentSchool();
        $prefix = 'TR-' . strtoupper($school->code) . '-';

        $last = Transcript::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('reference', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('reference');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
