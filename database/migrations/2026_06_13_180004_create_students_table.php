<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('uid', 30)->unique();                           // "AIA-2026-000412"
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('first_name_am', 100)->nullable();              // Amharic name (optional)
            $table->string('last_name_am', 100)->nullable();
            $table->enum('gender', ['male', 'female']);
            $table->date('date_of_birth')->nullable();                     // Stored as Gregorian always
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->string('photo_path', 500)->nullable();                 // Storage facade path
            $table->enum('blood_type', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])->nullable();
            $table->text('medical_notes')->nullable();
            $table->date('enrollment_date')->useCurrent();
            $table->boolean('is_active')->default(true);                   // FALSE = withdrawn/graduated
            $table->string('qr_token', 255)->nullable();                   // HMAC-SHA256 token for current year
            $table->string('qr_token_year', 20)->nullable();               // Academic year this token is valid for
            $table->timestamps();

            $table->index(['school_id', 'class_id']);
            $table->index(['school_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
