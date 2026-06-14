<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();  // NULL = whole school
            $table->enum('session_type', ['app_capture', 'zip_upload'])->default('app_capture');
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->json('student_queue');                                  // Ordered list of student IDs
            $table->integer('total_students');
            $table->integer('photos_captured')->default(0);
            $table->integer('current_position')->default(0);               // Tracks photographer position in queue
            $table->timestamps();
            $table->timestamp('completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_sessions');
    }
};
