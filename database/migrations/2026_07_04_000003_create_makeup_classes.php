<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makeup / extra classes — one-off, date-specific lessons that sit OUTSIDE the
 * recurring weekly timetable (the industry-standard "template + dated
 * exception" model). Used when a school needs to cover material after a
 * holiday, a cancelled period, or an ad-hoc revision session, without touching
 * the permanent weekly grid. Teachers see them for the specific date only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('makeup_classes')) {
            return;
        }

        Schema::create('makeup_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date');                       // the specific calendar day
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room', 50)->nullable();
            $table->string('reason', 255)->nullable();  // e.g. "Makeup for Meskel holiday"
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'date']);
            $table->index(['teacher_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('makeup_classes');
    }
};
