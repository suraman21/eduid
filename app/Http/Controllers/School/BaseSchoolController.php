<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolClass;

/**
 * Base controller for all school-facing (tenant) controllers.
 *
 * Tenant isolation is handled automatically by the BelongsToSchool global
 * scope on every school-owned model, so controllers do NOT need to filter
 * queries by school_id manually — the scope guarantees a non-super-admin
 * user only ever sees their own school's rows, and implicit route-model
 * binding returns 404 (not 403) for cross-tenant access.
 *
 * This base only provides small convenience accessors plus a write guard.
 */
abstract class BaseSchoolController extends Controller
{
    /**
     * The authenticated user's school id.
     */
    protected function schoolId(): int
    {
        return auth()->user()->school_id;
    }

    /**
     * The authenticated user's school model.
     */
    protected function currentSchool(): School
    {
        return auth()->user()->school;
    }

    /**
     * Abort unless the current user is a school administrator.
     *
     * Used as a server-side write guard — hiding buttons in Blade is not
     * sufficient, teachers/staff could otherwise POST directly.
     */
    protected function requireAdmin(): void
    {
        if (! auth()->user()->isSchoolAdmin()) {
            abort(403, 'Only school administrators can perform this action.');
        }
    }

    /**
     * Abort unless the school can accept new data (subscription guard).
     */
    protected function requireWritableSubscription(): void
    {
        if (! $this->currentSchool()->canAddNewData()) {
            abort(403, 'Your subscription has expired. New data entry is restricted.');
        }
    }

    /**
     * Abort 403 when a teacher tries to access a class they are not assigned to.
     * Admins and staff pass through unconditionally.
     *
     * Accepts null (e.g. a student not yet placed in any class): callers pass
     * $student->class_id directly, and a non-nullable parameter turned that
     * into a TypeError 500 for teachers. A classless student belongs to no
     * teacher's roster, so null → 403 for teachers.
     */
    protected function requireTeacherCanAccessClass(?int $classId): void
    {
        $user = auth()->user();
        if (! $user->isTeacher()) {
            return;
        }

        if ($classId === null
            || ! $user->assignedClasses()->where('classes.id', $classId)->exists()
        ) {
            abort(403, 'You are not assigned to this class.');
        }
    }

    /**
     * Active classes scoped to what the current user may see:
     * - teachers → only their assigned classes
     * - admins / staff → all active classes
     */
    protected function classesForCurrentUser(): \Illuminate\Database\Eloquent\Collection
    {
        $user  = auth()->user();
        $query = SchoolClass::where('is_active', true)
            ->orderBy('level_order')->orderBy('grade')->orderBy('section');

        if ($user->isTeacher()) {
            $query->whereHas('teachers', fn ($q) => $q->where('user_id', $user->id));
        }

        return $query->get();
    }
}
