<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Shared helpers for the mobile (Flutter) API controllers.
 *
 * Tenant isolation is automatic via the BelongsToSchool global scope (the
 * Sanctum guard becomes the default guard once auth:sanctum passes), so these
 * controllers never filter by school_id by hand. On top of that, teachers are
 * additionally restricted to the classes they are assigned to.
 */
abstract class BaseApiController extends Controller
{
    protected function user(): User
    {
        return auth()->user();
    }

    protected function currentSchool(): School
    {
        return $this->user()->school;
    }

    /**
     * Classes the authenticated user may act on:
     *   teachers → only their assigned classes
     *   admins   → every active class in the school
     */
    protected function classesForUser(): Collection
    {
        $user  = $this->user();
        $query = SchoolClass::where('is_active', true)
            ->orderBy('level_order')
            ->orderBy('grade');

        if ($user->isTeacher()) {
            $query->whereHas('teachers', fn ($q) => $q->where('user_id', $user->id));
        }

        return $query->get();
    }

    /**
     * Whether the user may record attendance for the given class id.
     * Admins: any class in their school. Teachers: assigned classes only.
     */
    protected function canAccessClass(int $classId): bool
    {
        $user = $this->user();

        // Class id must resolve within the school (global scope enforces this).
        if (! SchoolClass::whereKey($classId)->exists()) {
            return false;
        }

        if ($user->isTeacher()) {
            return $user->assignedClasses()->where('classes.id', $classId)->exists();
        }

        return true;
    }
}
