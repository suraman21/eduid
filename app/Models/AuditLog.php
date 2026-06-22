<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'school_id',
        'user_id',
        'action',
        'target_type',
        'target_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Factory Method ──────────────────────────────────────────

    /**
     * Create an audit log entry.
     */
    public static function log(
        string  $action,
        string  $targetType,
        ?int    $targetId = null,
        ?array  $oldValues = null,
        ?array  $newValues = null
    ): self {
        try {
            $ip        = request()->ip();
            $userAgent = request()->userAgent();
        } catch (\Throwable) {
            $ip        = null;
            $userAgent = 'cli';
        }

        return static::create([
            'school_id'   => auth()->check() ? auth()->user()->school_id : null,
            'user_id'     => auth()->id(),
            'action'      => $action,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'old_values'  => $oldValues,
            'new_values'  => $newValues,
            'ip_address'  => $ip,
            'user_agent'  => $userAgent,
        ]);
    }

    /**
     * Create an audit log entry with specific school/user context.
     */
    public static function createRecord(
        ?int    $schoolId,
        ?int    $userId,
        string  $action,
        string  $targetType,
        ?int    $targetId = null,
        ?array  $oldValues = null,
        ?array  $newValues = null
    ): self {
        // request() throws outside an HTTP context (queue jobs, artisan commands).
        try {
            $ip        = request()->ip();
            $userAgent = request()->userAgent();
        } catch (\Throwable) {
            $ip        = null;
            $userAgent = 'cli';
        }

        return static::create([
            'school_id'   => $schoolId,
            'user_id'     => $userId,
            'action'      => $action,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'old_values'  => $oldValues,
            'new_values'  => $newValues,
            'ip_address'  => $ip,
            'user_agent'  => $userAgent,
        ]);
    }

    /**
     * Safe audit log — identical to createRecord() but NEVER throws.
     * Use this anywhere a failed log must not crash the main operation
     * (login, logout, lockout, etc.).
     */
    public static function safeRecord(
        ?int    $schoolId,
        ?int    $userId,
        string  $action,
        string  $targetType,
        ?int    $targetId = null,
        ?array  $oldValues = null,
        ?array  $newValues = null
    ): ?self {
        try {
            return static::createRecord($schoolId, $userId, $action, $targetType, $targetId, $oldValues, $newValues);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Audit log failed', [
                'action'  => $action,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }
    }
}
