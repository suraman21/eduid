<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programmes — the layer above classes.
 *
 * A primary/secondary school creates one programme per stream ("Primary",
 * "Secondary"). A college creates one per award ("Diploma in ICT"). A course
 * centre creates one per course ("Spoken English Level 2"). Classes then hang
 * off a programme, which is what lets the same schema describe a Grade 9
 * section, a semester batch, and a weekend intake.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('programmes')) {
            return;
        }

        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();

            $table->string('code', 20);                       // "PRIM", "DIP-ICT"
            $table->string('name', 150);                      // "Primary", "Diploma in ICT"
            $table->enum('level_type', [
                'primary', 'secondary', 'college', 'course_center', 'other',
            ])->default('other');

            $table->string('award', 100)->nullable();          // "Certificate", "Diploma" — null for K-12
            $table->unsignedSmallInteger('duration_months')->nullable();
            $table->unsignedSmallInteger('total_levels')->nullable();   // #grades or #semesters

            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'code']);
            $table->index(['school_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programmes');
    }
};
