<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->date('session_date');                                   // Gregorian always
            $table->enum('session_type', ['morning', 'afternoon', 'full_day'])->default('morning');
            $table->enum('method', ['qr_scan', 'manual'])->default('qr_scan');
            $table->boolean('synced_from_app')->default(false);            // TRUE if created offline then synced
            $table->string('device_id', 100)->nullable();                  // Identifies which phone synced this
            $table->string('idempotency_key', 100)->nullable()->unique();  // Prevents duplicate sync submissions
            $table->timestamps();

            $table->unique(['school_id', 'class_id', 'session_date', 'session_type'], 'unique_session_per_slot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
