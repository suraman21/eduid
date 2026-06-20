<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_sessions', 'recorded_on_behalf_of')) {
                $table->foreignId('recorded_on_behalf_of')->nullable()->after('teacher_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('attendance_sessions', 'period_number')) {
                $table->smallInteger('period_number')->nullable()->after('recorded_on_behalf_of');
            }
        });
    }

    public function down(): void {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_sessions', 'recorded_on_behalf_of')) {
                $table->dropForeign(['recorded_on_behalf_of']);
                $table->dropColumn('recorded_on_behalf_of');
            }
            if (Schema::hasColumn('attendance_sessions', 'period_number')) {
                $table->dropColumn('period_number');
            }
        });
    }
};
