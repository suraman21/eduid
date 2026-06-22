<?php

namespace App\Http\Controllers\School;

use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Guardian management, scoped to a student. Guardians are a many-to-many
 * resource (a parent may have several children at the same school), so we
 * reuse an existing guardian row when the phone number matches, otherwise
 * create a new one. The pivot carries the per-student `is_primary` flag.
 */
class GuardianController extends BaseSchoolController
{
    private const RELATIONSHIPS = ['father', 'mother', 'guardian', 'sibling', 'other'];

    /**
     * Attach a guardian to a student (creating the guardian if new).
     */
    public function store(Request $request, Student $student)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $validated = $request->validate([
            'full_name'    => 'required|string|max:200',
            'phone'        => 'nullable|string|max:20',
            'phone_alt'    => 'nullable|string|max:20',
            'email'        => 'nullable|email|max:100',
            'relationship' => ['required', Rule::in(self::RELATIONSHIPS)],
            'is_primary'   => 'nullable|boolean',
        ]);

        $isPrimary = (bool) ($validated['is_primary'] ?? false);

        DB::transaction(function () use ($validated, $student, $isPrimary) {
            // Reuse an existing guardian within this school if the phone matches
            // (same parent, multiple children). Otherwise create a new record.
            $guardian = null;
            if (! empty($validated['phone'])) {
                $guardian = Guardian::where('phone', $validated['phone'])->first();
            }

            if ($guardian) {
                $guardian->update([
                    'full_name'    => $validated['full_name'],
                    'phone_alt'    => $validated['phone_alt'] ?? $guardian->phone_alt,
                    'email'        => $validated['email'] ?? $guardian->email,
                    'relationship' => $validated['relationship'],
                ]);
            } else {
                $guardian = Guardian::create([
                    'full_name'    => $validated['full_name'],
                    'phone'        => $validated['phone'] ?? null,
                    'phone_alt'    => $validated['phone_alt'] ?? null,
                    'email'        => $validated['email'] ?? null,
                    'relationship' => $validated['relationship'],
                ]);
            }

            // If this is the new primary, demote any existing primary first.
            if ($isPrimary) {
                $student->guardians()->updateExistingPivot(
                    $student->guardians()->pluck('guardians.id')->all(),
                    ['is_primary' => false]
                );
            }

            // syncWithoutDetaching keeps other guardian links intact.
            $student->guardians()->syncWithoutDetaching([
                $guardian->id => ['is_primary' => $isPrimary],
            ]);
        });

        AuditLog::log('guardian.linked', 'students', $student->id, null, [
            'guardian'   => $validated['full_name'],
            'is_primary' => $isPrimary,
        ]);

        return back()->with('success', 'Guardian added.');
    }

    /**
     * Update a guardian's details and/or its primary flag for this student.
     */
    public function update(Request $request, Student $student, Guardian $guardian)
    {
        $this->requireAdmin();

        // Ensure the guardian is actually linked to this student.
        abort_unless($student->guardians()->where('guardians.id', $guardian->id)->exists(), 404);

        $validated = $request->validate([
            'full_name'    => 'required|string|max:200',
            'phone'        => 'nullable|string|max:20',
            'phone_alt'    => 'nullable|string|max:20',
            'email'        => 'nullable|email|max:100',
            'relationship' => ['required', Rule::in(self::RELATIONSHIPS)],
            'is_primary'   => 'nullable|boolean',
        ]);

        $isPrimary = (bool) ($validated['is_primary'] ?? false);

        DB::transaction(function () use ($validated, $student, $guardian, $isPrimary) {
            $guardian->update([
                'full_name'    => $validated['full_name'],
                'phone'        => $validated['phone'] ?? null,
                'phone_alt'    => $validated['phone_alt'] ?? null,
                'email'        => $validated['email'] ?? null,
                'relationship' => $validated['relationship'],
            ]);

            if ($isPrimary) {
                $student->guardians()->updateExistingPivot(
                    $student->guardians()->pluck('guardians.id')->all(),
                    ['is_primary' => false]
                );
            }

            $student->guardians()->updateExistingPivot($guardian->id, ['is_primary' => $isPrimary]);
        });

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

            // Orphan cleanup: remove the guardian if no students reference it.
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
