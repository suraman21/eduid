<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'logo_path',
        'address',
        'phone',
        'email',
        'calendar_system',
        'academic_year',
        'hmac_secret',
        'is_active',
        'plan_tier',
        'max_students',
        'max_teachers',
        'subscription_status',
        'subscription_expires_at',
        'grace_period_ends_at',
        'attendance_mode',
        'accent_color',
        'offered_levels',
        'code_locked',
        'student_attendance_method',
        'staff_attendance_method',
        'onboarding_completed_at',
        'working_days',
        'attendance_cadence',
    ];

    /** Default when a school hasn't chosen its week: Mon–Sat (ISO 1..6). */
    public const DEFAULT_WORKING_DAYS = [1, 2, 3, 4, 5, 6];

    /**
     * Never serialize the QR-signing secret. Every student QR token is an
     * HMAC keyed by this value, so it must never reach JSON/Inertia/the client —
     * leaking it would let anyone forge scannable cards. Hidden here as a hard
     * backstop even if a controller accidentally returns a raw School model.
     */
    protected $hidden = [
        'hmac_secret',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'max_students' => 'integer',
            'max_teachers' => 'integer',
            'subscription_status' => 'string',
            'subscription_expires_at' => 'datetime',
            'grace_period_ends_at' => 'datetime',
            'attendance_mode' => 'string',
            'offered_levels' => 'array',
            'code_locked' => 'boolean',
            'onboarding_completed_at' => 'datetime',
            'student_attendance_method' => 'string',
            'staff_attendance_method' => 'string',
            'working_days' => 'array',
        ];
    }

    /** Valid attendance cadences (how many sessions per day). */
    public const CADENCES = ['once', 'twice', 'per_period'];

    /** Session types the school's cadence offers on attendance forms. */
    public function sessionTypes(): array
    {
        return match ($this->attendance_cadence ?? 'twice') {
            'once'  => ['full_day'],
            default => ['morning', 'afternoon'],
        };
    }

    /**
     * The school's working days as a sorted array of ISO weekday numbers
     * (1=Mon … 7=Sun). Falls back to Mon–Sat when unset, and always returns a
     * clean, de-duplicated, in-range list so callers never guard it themselves.
     *
     * @return array<int, int>
     */
    public function workingDays(): array
    {
        $days = $this->working_days;

        if (! is_array($days) || empty($days)) {
            return self::DEFAULT_WORKING_DAYS;
        }

        $clean = collect($days)
            ->map(fn ($d) => (int) $d)
            ->filter(fn ($d) => $d >= 1 && $d <= 7)
            ->unique()->sort()->values()->all();

        return empty($clean) ? self::DEFAULT_WORKING_DAYS : $clean;
    }

    // ── Relationships ──────────────────────────────────────────

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    public function cardOrders(): HasMany
    {
        return $this->hasMany(CardOrder::class);
    }

    public function photoSessions(): HasMany
    {
        return $this->hasMany(PhotoSession::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function featureOverrides(): HasMany
    {
        return $this->hasMany(SchoolFeatureOverride::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    // ── Subscription Plan ────────────────────────────────────────

    public function currentPlan(): ?SubscriptionPlan
    {
        return SubscriptionPlan::where('tier', $this->plan_tier)->first();
    }

    // ── Helpers ─────────────────────────────────────────────────

    /**
     * Get logo URL via Storage facade.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::url($this->logo_path);
    }

    /**
     * Count of active students for this school.
     *
     * "Active" is determined by the `status` column (introduced in the
     * alter_students_add_status migration). The old `is_active` boolean is
     * kept for backwards-compatibility but the status column is authoritative.
     * Only 'active' status counts against the plan cap — promoted/graduated/
     * transferred students no longer occupy a seat.
     */
    public function activeStudentCount(): int
    {
        return $this->students()->where('status', 'active')->count();
    }

    /**
     * Count of active teachers for this school.
     */
    public function activeTeacherCount(): int
    {
        return $this->users()->where('role', 'teacher')->where('is_active', true)->count();
    }

    /**
     * Generate a new HMAC secret for this school.
     */
    public static function generateHmacSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    // ── Subscription Status Helpers ──────────────────────────────

    public function isSubscriptionActive(): bool
    {
        return $this->subscription_status === 'active';
    }

    public function isInGrace(): bool
    {
        return $this->subscription_status === 'grace';
    }

    public function isExpired(): bool
    {
        return $this->subscription_status === 'expired' || $this->subscription_status === 'suspended';
    }

    public function canAddNewData(): bool
    {
        return ! ($this->isInGrace() || $this->isExpired());
    }

    /**
     * Check if the school has access to a given feature key.
     *
     * Resolution order:
     *  1. school_feature_overrides (per-school override)
     *  2. plan_features (plan-level default)
     *  3. feature_registry (global default)
     */
    /**
     * Check if the school has access to a given feature key.
     *
     * Resolution order:
     *  1. school_feature_overrides (per-school override — highest priority)
     *  2. plan_features            (plan-level default)
     *  3. feature_registry         (global default — lowest priority)
     *
     * Results are cached for 1 hour via the application cache (Redis/file).
     * Call `$school->flushFeatureCache()` after updating overrides.
     */
    public function hasFeature(string $key): bool
    {
        return Cache::remember(
            "school.{$this->id}.feature.{$key}",
            now()->addHour(),
            function () use ($key) {
                // 1. School-level override
                $override = DB::table('school_feature_overrides')
                    ->where('school_id', $this->id)
                    ->where('feature_key', $key)
                    ->first();

                if ($override !== null) {
                    return (bool) $override->is_enabled;
                }

                // 2. Plan-level feature
                $planFeature = DB::table('plan_features')
                    ->where('plan_tier', $this->plan_tier)
                    ->where('feature_key', $key)
                    ->first();

                if ($planFeature !== null) {
                    return true;
                }

                // 3. Feature registry global default
                $registry = DB::table('feature_registry')
                    ->where('key', $key)
                    ->first();

                return $registry ? (bool) $registry->is_default_enabled : false;
            }
        );
    }

    /**
     * Flush the feature cache for this school.
     * Call this whenever a school's feature overrides or plan is changed.
     */
    public function flushFeatureCache(): void
    {
        // Cache::forget() does not support wildcards, so the previous "*" call was
        // a silent no-op. Enumerate every registered feature key and forget each
        // exact cache entry for this school. The registry list is tiny and itself
        // cached, so this stays cheap even on the file driver.
        $keys = Cache::remember(
            'feature_registry.keys',
            now()->addHour(),
            fn () => FeatureRegistry::pluck('key')->all()
        );

        foreach ($keys as $key) {
            Cache::forget("school.{$this->id}.feature.{$key}");
        }
    }
}
