<?php

namespace App\Http\Controllers\School;

use App\Models\AuditLog;
use App\Models\StaffDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Staff paperwork: contracts, licences, clearances, permits.
 *
 * The student vault is a records cupboard — you file a birth certificate and
 * it is done. Staff paperwork is not like that: a teaching licence, a police
 * clearance, a medical certificate and a work permit all lapse, and the school
 * is the party that has to notice. So the register here is ordered by urgency
 * rather than by upload date, and a document with no expiry date is treated as
 * a document nobody is watching.
 */
class StaffDocumentController extends BaseSchoolController
{
    /** School-wide compliance register. */
    public function index(Request $request)
    {
        $state = $request->query('state');          // expired | expiring | valid | none
        $type  = $request->query('type');
        $q     = trim((string) $request->query('q', ''));

        $base = StaffDocument::query()
            ->with(['staff:id,name,role,job_title,employee_id,is_active'])
            ->when($type && array_key_exists($type, StaffDocument::TYPES), fn ($qq) => $qq->where('type', $type))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('title', 'like', "%{$q}%")
                      ->orWhereHas('staff', function ($s) use ($q) {
                          $s->where('name', 'like', "%{$q}%")
                            ->orWhere('employee_id', 'like', "%{$q}%");
                      });
                });
            });

        // "All" is ordered by urgency: expired first, then soonest expiry.
        $documents = (clone $base)
            ->when($state === 'expired', fn ($qq) => $qq->whereDate('expires_on', '<', now()->toDateString()))
            ->when($state === 'expiring', fn ($qq) => $qq
                ->whereDate('expires_on', '>=', now()->toDateString())
                ->whereDate('expires_on', '<=', now()->addDays(StaffDocument::WARN_DAYS)->toDateString()))
            ->when($state === 'valid', fn ($qq) => $qq
                ->whereDate('expires_on', '>', now()->addDays(StaffDocument::WARN_DAYS)->toDateString()))
            ->when($state === 'none', fn ($qq) => $qq->whereNull('expires_on'))
            ->orderByRaw('CASE WHEN expires_on IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_on')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $counts = [
            'expired'  => StaffDocument::query()->whereDate('expires_on', '<', now()->toDateString())->count(),
            'expiring' => StaffDocument::query()
                ->whereDate('expires_on', '>=', now()->toDateString())
                ->whereDate('expires_on', '<=', now()->addDays(StaffDocument::WARN_DAYS)->toDateString())
                ->count(),
            'none'     => StaffDocument::query()->whereNull('expires_on')->count(),
            'total'    => StaffDocument::query()->count(),
        ];
        $counts['valid'] = $counts['total'] - $counts['expired'] - $counts['expiring'] - $counts['none'];

        // Which staff hold nothing at all? The most common real gap is a new
        // hire whose contract was never filed.
        $staffWithoutDocs = User::query()
            ->whereIn('role', ['school_admin', 'teacher', 'staff'])
            ->where('is_active', true)
            ->whereDoesntHave('staffDocuments')
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'job_title'])
            ->map(fn ($u) => [
                'id'        => $u->id,
                'name'      => $u->name,
                'role'      => $u->role,
                'job_title' => $u->job_title,
            ]);

        return Inertia::render('School/StaffDocuments/Index', [
            'documents' => $documents->through(fn ($d) => $this->row($d)),
            'counts'    => $counts,
            'staff_without_documents' => $staffWithoutDocs,
            'types'     => collect(StaffDocument::TYPES)->map(fn ($l, $k) => ['value' => $k, 'label' => $l])->values(),
            'filters'   => ['state' => $state, 'type' => $type, 'q' => $q],
            'warn_days' => StaffDocument::WARN_DAYS,
            'is_admin'  => auth()->user()?->isSchoolAdmin() ?? false,
        ]);
    }

    /** Upload a document against one staff member. */
    public function store(Request $request, User $staff)
    {
        $this->requireAdmin();
        $this->assertSameSchool($staff);

        $data = $request->validate([
            'type'        => ['required', Rule::in(array_keys(StaffDocument::TYPES))],
            'title'       => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'issued_on'   => ['nullable', 'date'],
            'expires_on'  => ['nullable', 'date', 'after_or_equal:issued_on'],
            'file'        => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $file = $request->file('file');
        $path = $file->store("staff/{$staff->school_id}/{$staff->id}/documents", 'local');

        $document = StaffDocument::create([
            'school_id'     => $staff->school_id,
            'user_id'       => $staff->id,
            'type'          => $data['type'],
            'title'         => $data['title'],
            'description'   => $data['description'] ?? null,
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'file_size'     => $file->getSize(),
            'issued_on'     => $data['issued_on'] ?? null,
            'expires_on'    => $data['expires_on'] ?? null,
            'uploaded_by'   => auth()->id(),
        ]);

        AuditLog::log('staff.document_uploaded', 'staff_documents', $document->id, null, [
            'staff' => $staff->name,
            'type'  => $document->type,
        ]);

        return back()->with('success', "{$document->typeLabel()} filed for {$staff->name}.");
    }

    /** Stream a document through an authenticated, tenant-scoped route. */
    public function download(User $staff, StaffDocument $document)
    {
        $this->assertSameSchool($staff);
        abort_unless($document->user_id === $staff->id, 404);

        if (! Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'File missing.');
        }

        return Storage::disk('local')->response(
            $document->file_path,
            $document->original_name ?: $document->title,
        );
    }

    /** Mark a document as checked against the original. */
    public function verify(User $staff, StaffDocument $document)
    {
        $this->requireAdmin();
        $this->assertSameSchool($staff);
        abort_unless($document->user_id === $staff->id, 404);

        $document->update([
            'is_verified' => ! $document->is_verified,
            'verified_by' => $document->is_verified ? null : auth()->id(),
            'verified_at' => $document->is_verified ? null : now(),
        ]);

        return back()->with('success', $document->is_verified
            ? 'Marked as verified.'
            : 'Verification removed.');
    }

    /** Remove a file. The audit entry stays. */
    public function destroy(User $staff, StaffDocument $document)
    {
        $this->requireAdmin();
        $this->assertSameSchool($staff);
        abort_unless($document->user_id === $staff->id, 404);

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        AuditLog::log('staff.document_deleted', 'staff_documents', $document->id, [
            'title' => $document->title,
            'staff' => $staff->name,
        ]);

        return back()->with('success', 'Document removed.');
    }

    // ── Helpers ────────────────────────────────────────────────

    private function assertSameSchool(User $staff): void
    {
        abort_unless((int) $staff->school_id === $this->schoolId(), 404);
    }

    private function row(StaffDocument $d): array
    {
        return [
            'id'           => $d->id,
            'type'         => $d->type,
            'type_label'   => $d->typeLabel(),
            'title'        => $d->title,
            'description'  => $d->description,
            'file_name'    => $d->original_name,
            'file_size'    => $d->humanSize(),
            'is_pdf'       => $d->isPdf(),
            'is_image'     => $d->isImage(),
            'issued_on'    => $d->issued_on?->format('Y-m-d'),
            'expires_on'   => $d->expires_on?->format('Y-m-d'),
            'expiry_state' => $d->expiryState(),
            'days_left'    => $d->daysUntilExpiry(),
            'is_expired'   => $d->isExpired(),
            'is_verified'  => (bool) $d->is_verified,
            'verified_at'  => $d->verified_at?->format('Y-m-d'),
            'uploaded_by'  => $d->uploader?->name,
            'staff'        => [
                'id'          => $d->staff?->id,
                'name'        => $d->staff?->name,
                'role'        => $d->staff?->role,
                'job_title'   => $d->staff?->job_title,
                'employee_id' => $d->staff?->employee_id,
                'is_active'   => (bool) $d->staff?->is_active,
            ],
        ];
    }
}
