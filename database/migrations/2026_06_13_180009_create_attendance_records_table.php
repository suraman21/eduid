<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('status', ['present', 'absent', 'late', 'excused']);
            $table->timestamp('scanned_at')->nullable();                   // Exact scan time (from phone clock)
            $table->string('note', 200)->nullable();                       // "arrived 10 min late"
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['session_id', 'student_id']);                   // One record per student per session
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
