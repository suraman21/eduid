<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('students', function (Blueprint $table) {
            $table->enum('status', ['active','promoted','repeating','graduated','transferred','withdrawn'])->default('active')->after('is_active');
            $table->date('join_date')->nullable()->after('status');
            $table->date('end_date')->nullable()->after('join_date');
        });
        DB::statement("UPDATE students SET status = 'withdrawn' WHERE is_active = 0");
    }

    public function down(): void {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['status','join_date','end_date']);
        });
    }
};
