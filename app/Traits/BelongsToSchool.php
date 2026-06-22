<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * BelongsToSchool Trait — Multi-Tenant Data Isolation
 *
 * Add this trait to every Eloquent model that has a `school_id` column.
 * It auto-scopes all queries by the authenticated user's school_id,
 * making it architecturally impossible for School A to access School B's data.
 *
 * Super Admin (role = 'super_admin') bypasses the scope and sees all schools.
 */
trait BelongsToSchool
{
    protected static function bootBelongsToSchool(): void
    {
        // Auto-scope all queries by school_id
        static::addGlobalScope('school', function (Builder $query) {
            // Use hasUser() instead of check() to avoid infinite recursion.
            //
            // auth()->check() resolves the user by querying the User model.
            // If User itself has this trait, that query triggers the scope again,
            // which calls check() again — infinite loop until PHP's execution
            // time limit kills the request (observed as a 300s timeout).
            //
            // hasUser() only inspects the already-resolved guard property; it
            // never hits the DB. Unauthenticated requests and CLI/queue contexts
            // both return false here, so no scope is applied — same behaviour as
            // before but without the recursive query.
            if (!auth()->hasUser()) {
                return;
            }

            $user = auth()->user();

            if ($user->role !== 'super_admin') {
                $query->where(
                    $query->getModel()->getTable() . '.school_id',
                    $user->school_id
                );
            }
        });

        // Auto-set school_id on create when not explicitly provided
        static::creating(function ($model) {
            if (auth()->hasUser() && !$model->school_id && auth()->user()->role !== 'super_admin') {
                $model->school_id = auth()->user()->school_id;
            }
        });
    }

    /**
     * Relationship: this model belongs to a School.
     */
    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\School::class);
    }
}
