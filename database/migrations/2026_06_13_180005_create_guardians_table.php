<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('full_name', 200);
            $table->string('phone', 20)->nullable();
            $table->string('phone_alt', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->enum('relationship', ['father', 'mother', 'guardian', 'sibling', 'other'])->default('guardian');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};
