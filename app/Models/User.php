<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, BelongsToSchool;

    protected $fillable = [
        'school_id',
        'name',
        'email',
        'password',
        'role',
        'phone',
        'is_active',
        'last_login_at',
        'failed_login_count',
        'locked_until',
        'employee_id',
        'job_title',
        'is_homeroom',
        'is_coordinator',
        'can_record_staff_attendance',
        'can_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'           => 'datetime',
            'password'                    => 'hashed',
            'is_active'                   => 'boolean',
            'last_login_at'               => 'datetime',
            'locked_until'                => 'datetime',
            'failed_login_count'          => 'integer',
            'is_homeroom'                 => 'boolean',
            'is_coordinator'              => 'boolean',
            'can_record_staff_attendance' => 'boolean',
            'can_login'                   => 'boolean',
        ];
    }

    // ── Role Constants ──────────────────────────────────────────

    const ROLE_SUPER_ADMIN  = 'super_admin';
    const ROLE_SCHOOL_ADMIN = 'school_admin';
    const ROLE_TEACHER      = 'teacher';

    // ── Role Helpers ────────────────────────────────────────────

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isSchoolAdmin(): bool
    {
        return $this->role === self::ROLE_SCHOOL_ADMIN;
    }

    public function isTeacher(): bool
    {
        return $this->role === self::ROLE_TEACHER;
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    public function isHomeroom(): bool
    {
        return (bool) $this->is_homeroom;
    }

    public function isCoordinator(): bool
    {
        return (bool) $this->is_coordinator;
    }

    public function canRecordStaffAttendance(): bool
    {
        return (bool) $this->can_record_staff_attendance;
    }

    public function hasLoginAccess(): bool
    {
        return $this->is_active && $this->can_login;
    }

    // ── Account Lockout ─────────────────────────────────────────

    /**
     * Check if the account is currently locked.
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Increment failed login count and lock if threshold reached.
     */
    public function incrementFailedLogins(): void
    {
        $this->increment('failed_login_count');

        if ($this->failed_login_count >= 5) {
            $this->update([
                'locked_until' => now()->addMinutes(30),
            ]);
        }
    }

    /**
     * Reset failed login tracking on successful login.
     */
    public function resetFailedLogins(): void
    {
        $this->update([
            'failed_login_count' => 0,
            'locked_until'       => null,
            'last_login_at'      => now(),
        ]);
    }

    // ── Relationships ──────────────────────────────────────────

    public function assignedClasses(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'teacher_classes', 'user_id', 'class_id');
    }

    public function attendanceSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'teacher_id');
    }

    public function cardOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CardOrder::class, 'ordered_by');
    }

    public function auditLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
