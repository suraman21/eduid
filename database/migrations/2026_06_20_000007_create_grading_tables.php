<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('grading_scales')) {
            Schema::create('grading_scales', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->string('label', 10);
                $table->decimal('min_score', 5, 2);
                $table->decimal('max_score', 5, 2);
                $table->decimal('gpa_point', 3, 2)->nullable();
                $table->string('description', 100)->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('assessment_configs')) {
            Schema::create('assessment_configs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->string('academic_year', 20);
                $table->string('grade_level', 50)->nullable();
                $table->decimal('ca_weight', 5, 2)->default(40);
                $table->decimal('midterm_weight', 5, 2)->default(20);
                $table->decimal('final_weight', 5, 2)->default(40);
                $table->timestamps();
                $table->unique(['school_id','academic_year','grade_level']);
            });
        }

        if (!Schema::hasTable('student_grades')) {
            Schema::create('student_grades', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->enum('assessment_type', ['ca','midterm','final']);
                $table->decimal('score', 5, 2);
                $table->decimal('max_score', 5, 2)->default(100);
                $table->foreignId('entered_by')->constrained('users')->cascadeOnDelete();
                $table->enum('status', ['draft','submitted','published'])->default('draft');
                $table->boolean('flagged')->default(false);
                $table->text('flag_note')->nullable();
                $table->string('reason', 500)->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->unique(['student_id','subject_id','term_id','assessment_type']);
            });
        }

        if (!Schema::hasTable('grade_submissions')) {
            Schema::create('grade_submissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('homeroom_approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('homeroom_approved_at')->nullable();
                $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->unique(['class_id','subject_id','term_id']);
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('grade_submissions');
        Schema::dropIfExists('student_grades');
        Schema::dropIfExists('assessment_configs');
        Schema::dropIfExists('grading_scales');
    }
};
