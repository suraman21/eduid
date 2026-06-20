<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('schools', function (Blueprint $table) {
            if (!Schema::hasColumn('schools', 'subscription_status')) {
                $table->enum('subscription_status', ['active','grace','expired','suspended'])->default('active')->after('is_active');
            }
            if (!Schema::hasColumn('schools', 'subscription_expires_at')) {
                $table->timestamp('subscription_expires_at')->nullable()->after('subscription_status');
            }
            if (!Schema::hasColumn('schools', 'grace_period_ends_at')) {
                $table->timestamp('grace_period_ends_at')->nullable()->after('subscription_expires_at');
            }
            if (!Schema::hasColumn('schools', 'attendance_mode')) {
                $table->enum('attendance_mode', ['homeroom','subject'])->default('homeroom')->after('grace_period_ends_at');
            }
            if (!Schema::hasColumn('schools', 'accent_color')) {
                $table->string('accent_color', 7)->default('#3B82F6')->after('attendance_mode');
            }
            if (!Schema::hasColumn('schools', 'offered_levels')) {
                $table->json('offered_levels')->nullable()->after('accent_color');
            }
            if (!Schema::hasColumn('schools', 'code_locked')) {
                $table->boolean('code_locked')->default(false)->after('offered_levels');
            }
            if (!Schema::hasColumn('schools', 'student_attendance_method')) {
                $table->enum('student_attendance_method', ['qr_only','manual_only','both'])->default('both')->after('code_locked');
            }
            if (!Schema::hasColumn('schools', 'staff_attendance_method')) {
                $table->enum('staff_attendance_method', ['qr_only','manual_only','both'])->default('both')->after('student_attendance_method');
            }
            if (!Schema::hasColumn('schools', 'onboarding_completed_at')) {
                $table->timestamp('onboarding_completed_at')->nullable()->after('staff_attendance_method');
            }
        });
    }

    public function down(): void {
        Schema::table('schools', function (Blueprint $table) {
            $cols = ['subscription_status','subscription_expires_at','grace_period_ends_at',
                'attendance_mode','accent_color','offered_levels','code_locked',
                'student_attendance_method','staff_attendance_method','onboarding_completed_at'];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('schools', $c));
            if ($existing) $table->dropColumn(array_values($existing));
        });
    }
};
