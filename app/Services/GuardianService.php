<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Centralises guardian resolution + linking so the student form and the
 * student-profile "add guardian" panel behave identically.
 *
 * Resolution order for a guardian payload:
 *   1. An explicit existing guardian id (picked from the typeahead).
 *   2. A phone-number match within the school (same parent, another child).
 *   3. Otherwise a brand-new guardian row.
 *
 * The per-student `is_primary` flag is single-valued: promoting one guardian
 * demotes the others for that student.
 */
class GuardianService
{
    public const RELATIONSHIPS = ['father', 'mother', 'guardian', 'sibling', 'other'];

    /**
     * Resolve (find-or-create) a guardian from a payload and link it to the
     * student. Returns the linked Guardian.
     *
     * @param array{id?:int|string|null,full_name:string,phone?:string,phone_alt?:string,email?:string,relationship:string} $data
     */
    public function attach(Student $student, array $data, bool $isPrimary = false): Guardian
    {
        return DB::transaction(function () use ($student, $data, $isPrimary) {
            $guardian = $this->resolveGuardian($data);

            if ($isPrimary) {
                $this->demoteExistingPrimary($student);
            }

            $student->guardians()->syncWithoutDetaching([
                $guardian->id => ['is_primary' => $isPrimary],
            ]);

            return $guardian;
        });
    }

    /**
     * Attach a list of guardian payloads to a student in one pass. The row at
     * $primaryIndex (if any) becomes the primary contact.
     *
     * @param array<int,array> $rows
     */
    public function attachMany(Student $student, array $rows, ?int $primaryIndex = null): void
    {
        foreach ($rows as $i => $row) {
            // Skip blank rows (a guardian must at least have a name).
            if (empty($row['full_name'])) {
                continue;
            }

            $this->attach($student, $row, $primaryIndex !== null && (int) $primaryIndex === (int) $i);
        }
    }

    /**
     * Find an existing guardian (by id, then by phone) or create a new one,
     * refreshing its contact details from the payload.
     */
    private function resolveGuardian(array $data): Guardian
    {
        $guardian = null;

        if (! empty($data['id'])) {
            // Scoped find — the global scope guarantees same-school only.
            $guardian = Guardian::find($data['id']);
        }

        if (! $guardian && ! empty($data['phone'])) {
            $guardian = Guardian::where('phone', $data['phone'])->first();
        }

        $attributes = [
            'full_name'    => $data['full_name'],
            'phone'        => $data['phone'] ?? ($guardian->phone ?? null),
            'phone_alt'    => $data['phone_alt'] ?? ($guardian->phone_alt ?? null),
            'email'        => $data['email'] ?? ($guardian->email ?? null),
            'relationship' => $data['relationship'] ?? ($guardian->relationship ?? 'guardian'),
        ];

        if ($guardian) {
            $guardian->update($attributes);

            return $guardian;
        }

        return Guardian::create($attributes);
    }

    private function demoteExistingPrimary(Student $student): void
    {
        $ids = $student->guardians()->pluck('guardians.id')->all();

        if ($ids) {
            $student->guardians()->updateExistingPivot($ids, ['is_primary' => false]);
        }
    }
}
