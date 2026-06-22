<?php

namespace App\Http\Controllers\School;

use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\GuardianService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Guardian management, scoped to a student. Guardians are a many-to-many
 * resource (a parent may have several children at the same school). The
 * find-or-create + primary logic lives in GuardianService so the inline
 * student-form flow and this profile-panel flow stay identical.
 */
class GuardianController extends BaseSchoolController
{
    public function __construct(private readonly GuardianService $guardians)
    {
    }

    /**
     * Typeahead search for existing guardians (sibling reuse). Matches on name
     * or phone, scoped to the current school by the global scope. Returns a
     * small JSON list for the form's autocomplete.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $matches = Guardian::query()
            ->where(fn ($w) => $w
                ->where('full_name', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%"))
            ->withCount('students')
            ->orderBy('full_name')
            ->limit(8)
            ->get(['id', 'full_name', 'phone', 'phone_alt', 'email', 'relationship']);

        return response()->json($matches->map(fn (Guardian $g) => [
            'id'             => $g->id,
            'full_name'      => $g->full_name,
            'phone'          => $g->phone,
            'phone_alt'      => $g->phone_alt,
            'email'          => $g->email,
            'relationship'   => $g->relationship,
            'students_count' => $g->students_count,
        ]));
    }

    /**
     * Attach a guardian to a student (creating or reusing as appropriate).
     */
    public function store(Request $request, Student $student)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $validated = $request->validate([
            'id'           => 'nullable|integer|exists:guardians,id',
            'full_name'    => 'required|string|max:200',
            'phone'        => 'nullable|string|max:20',
            'phone_alt'    => 'nullable|string|max:20',
            'email'        => 'nullable|email|max:100',
            'relationship' => ['required', Rule::in(GuardianService::RELATIONSHIPS)],
            'is_primary'   => 'nullable|boolean',
        ]);

        $this->guardians->attach($student, $validated, (bool) ($validated['is_primary'] ?? false));

        AuditLog::log('guardian.linked', 'students', $student->id, null, [
            'guardian' => $validated['full_name'],
        ]);

        return back()->with('success', 'Guardian added.');
    }

    /**
     * Update a guardian's details and/or its primary flag for this student.
     */
    public function update(Request $request, Student $student, Guardian $guardian)
    {
        $this->requireAdmin();

        abort_unless($student->guardians()->where('guardians.id', $guardian->id)->exists(), 404);

        $validated = $request->validate([
            'full_name'    => 'required|string|max:200',
            'phone'        => 'nullable|string|max:20',
            'phone_alt'    => 'nullable|string|max:20',
            'email'        => 'nullable|email|max:100',
            'relationship' => ['required', Rule::in(GuardianService::RELATIONSHIPS)],
            'is_primary'   => 'nullable|boolean',
        ]);

        // Reuse the service's resolve+primary logic by passing the known id.
        $this->guardians->attach(
            $student,
            array_merge($validated, ['id' => $guardian->id]),
            (bool) ($validated['is_primary'] ?? false)
        );

        AuditLog::log('guardian.updated', 'students', $student->id, null, [
            'guardian' => $guardian->full_name,
        ]);

        return back()->with('success', 'Guardian updated.');
    }

    /**
     * Detach a guardian from this student. The guardian row is deleted only
     * if it is no longer linked to any other student.
     */
    public function destroy(Student $student, Guardian $guardian)
    {
        $this->requireAdmin();

        abort_unless($student->guardians()->where('guardians.id', $guardian->id)->exists(), 404);

        DB::transaction(function () use ($student, $guardian) {
            $student->guardians()->detach($guardian->id);

            if ($guardian->students()->count() === 0) {
                $guardian->delete();
            }
        });

        AuditLog::log('guardian.unlinked', 'students', $student->id, null, [
            'guardian' => $guardian->full_name,
        ]);

        return back()->with('success', 'Guardian removed.');
    }
}
