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
            // Avoid infinite recursion when authenticating / retrieving the user
            if (static::class === \App\Models\User::class && !auth()->hasUser()) {
                return;
            }

            if (auth()->check() && auth()->user()->role !== 'super_admin') {
                $query->where(
                    $query->getModel()->getTable() . '.school_id',
                    auth()->user()->school_id
                );
            }
        });

        // Auto-set school_id on create when not explicitly provided
        static::creating(function ($model) {
            if (auth()->check() && !$model->school_id && auth()->user()->role !== 'super_admin') {
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
