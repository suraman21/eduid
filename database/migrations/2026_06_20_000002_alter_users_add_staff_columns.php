<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Extend role enum to include 'staff' (MySQL only; SQLite uses TEXT so no alter needed)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','school_admin','teacher','staff') NOT NULL DEFAULT 'teacher'");
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_id', 30)->nullable()->after('role');
            $table->string('job_title', 100)->nullable()->after('employee_id');
            $table->boolean('is_homeroom')->default(false)->after('job_title');
            $table->boolean('is_coordinator')->default(false)->after('is_homeroom');
            $table->boolean('can_record_staff_attendance')->default(false)->after('is_coordinator');
            $table->boolean('can_login')->default(true)->after('can_record_staff_attendance');

            // Unique index on school_id + employee_id
            $table->unique(['school_id', 'employee_id'], 'users_school_employee_unique');
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_school_employee_unique');
            $table->dropColumn(['employee_id','job_title','is_homeroom','is_coordinator','can_record_staff_attendance','can_login']);
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','school_admin','teacher') NOT NULL DEFAULT 'teacher'");
        }
    }
};
