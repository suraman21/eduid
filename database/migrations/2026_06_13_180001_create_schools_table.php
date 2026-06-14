<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('code', 10)->unique();                          // "AIA", "SJA" — used in login + UID
            $table->string('logo_path', 500)->nullable();                  // Storage path (via Storage facade)
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->enum('calendar_system', ['ethiopian', 'gregorian'])->default('ethiopian');
            $table->string('academic_year', 20);                           // "2026/2027" or "2018/2019 EC"
            $table->string('hmac_secret', 64);                             // Per-school QR signing key (auto-generated)
            $table->boolean('is_active')->default(true);
            $table->enum('plan_tier', ['starter', 'standard', 'premium'])->default('standard');
            $table->integer('max_students')->default(800);                 // Enforced limit per plan
            $table->integer('max_teachers')->default(30);                  // Enforced limit per plan
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
