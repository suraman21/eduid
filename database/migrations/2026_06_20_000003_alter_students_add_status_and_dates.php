<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'status')) {
                $table->enum('status', ['active','promoted','repeating','graduated','transferred','withdrawn'])->default('active')->after('is_active');
            }
            if (!Schema::hasColumn('students', 'join_date')) {
                $table->date('join_date')->nullable()->after('status');
            }
            if (!Schema::hasColumn('students', 'end_date')) {
                $table->date('end_date')->nullable()->after('join_date');
            }
        });
        DB::statement("UPDATE students SET status = 'withdrawn' WHERE is_active = 0 AND status = 'active'");
    }

    public function down(): void {
        Schema::table('students', function (Blueprint $table) {
            $cols = ['status','join_date','end_date'];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('students', $c));
            if ($existing) $table->dropColumn(array_values($existing));
        });
    }
};
