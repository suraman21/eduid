<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','school_admin','teacher','staff') NOT NULL DEFAULT 'teacher'");
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'employee_id')) {
                $table->string('employee_id', 30)->nullable()->after('role');
            }
            if (!Schema::hasColumn('users', 'job_title')) {
                $table->string('job_title', 100)->nullable()->after('employee_id');
            }
            if (!Schema::hasColumn('users', 'is_homeroom')) {
                $table->boolean('is_homeroom')->default(false)->after('job_title');
            }
            if (!Schema::hasColumn('users', 'is_coordinator')) {
                $table->boolean('is_coordinator')->default(false)->after('is_homeroom');
            }
            if (!Schema::hasColumn('users', 'can_record_staff_attendance')) {
                $table->boolean('can_record_staff_attendance')->default(false)->after('is_coordinator');
            }
            if (!Schema::hasColumn('users', 'can_login')) {
                $table->boolean('can_login')->default(true)->after('can_record_staff_attendance');
            }
        });

        // Add unique index only if it doesn't exist
        $indexes = collect(DB::select("SHOW INDEX FROM users WHERE Key_name = 'users_school_employee_unique'"));
        if ($indexes->isEmpty()) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique(['school_id', 'employee_id'], 'users_school_employee_unique');
            });
        }
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            try { $table->dropUnique('users_school_employee_unique'); } catch (\Exception $e) {}
            $cols = ['employee_id','job_title','is_homeroom','is_coordinator','can_record_staff_attendance','can_login'];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('users', $c));
            if ($existing) $table->dropColumn(array_values($existing));
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','school_admin','teacher') NOT NULL DEFAULT 'teacher'");
        }
    }
};
