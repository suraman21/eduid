<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('report_card_versions')) {
            Schema::create('report_card_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
                $table->integer('version_number')->default(1);
                $table->string('pdf_path', 500);
                $table->timestamp('generated_at');
                $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('report_card_versions');
    }
};
