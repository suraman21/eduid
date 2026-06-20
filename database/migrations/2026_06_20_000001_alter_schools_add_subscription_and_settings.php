<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('schools', function (Blueprint $table) {
            $table->enum('subscription_status', ['active','grace','expired','suspended'])->default('active')->after('is_active');
            $table->timestamp('subscription_expires_at')->nullable()->after('subscription_status');
            $table->timestamp('grace_period_ends_at')->nullable()->after('subscription_expires_at');
            $table->enum('attendance_mode', ['homeroom','subject'])->default('homeroom')->after('grace_period_ends_at');
            $table->string('accent_color', 7)->default('#3B82F6')->after('attendance_mode');
            $table->json('offered_levels')->nullable()->after('accent_color');
            $table->boolean('code_locked')->default(false)->after('offered_levels');
            $table->enum('student_attendance_method', ['qr_only','manual_only','both'])->default('both')->after('code_locked');
            $table->enum('staff_attendance_method', ['qr_only','manual_only','both'])->default('both')->after('student_attendance_method');
            $table->timestamp('onboarding_completed_at')->nullable()->after('staff_attendance_method');
        });
    }

    public function down(): void {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_status','subscription_expires_at','grace_period_ends_at',
                'attendance_mode','accent_color','offered_levels','code_locked',
                'student_attendance_method','staff_attendance_method','onboarding_completed_at',
            ]);
        });
    }
};
