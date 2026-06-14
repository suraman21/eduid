<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('grade', 20);                                   // "Grade 9", "KG-1", "Nursery"
            $table->string('section', 10);                                 // "A", "B", "C"
            $table->string('academic_year', 20);                           // "2026/2027"
            $table->integer('level_order')->default(0);                    // For sorting: KG=1, Grade1=2...Grade12=13
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'grade', 'section', 'academic_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
