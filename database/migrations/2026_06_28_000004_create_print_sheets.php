<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Virtual records of physical PVC sheets so a partially-printed sheet can be
 * reopened and filled from where it left off — no wasted stock. A sheet holds
 * one slot row per card position; occupied slots always form a contiguous
 * prefix (1..k) because assignment is enforced sequentially. Super-admin infra,
 * not tenant-scoped (a sheet may carry cards from one school batch at a time).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sheet_template_id')->constrained('sheet_templates')->restrictOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['open', 'completed'])->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'school_id']);
        });

        Schema::create('print_sheet_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_sheet_id')->constrained('print_sheets')->cascadeOnDelete();
            $table->unsignedTinyInteger('slot_number');
            $table->enum('status', ['empty', 'printed'])->default('empty');
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('card_order_id')->nullable()->constrained('card_orders')->nullOnDelete();
            $table->boolean('printed_front')->default(false);
            $table->boolean('printed_back')->default(false);
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->unique(['print_sheet_id', 'slot_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_sheet_slots');
        Schema::dropIfExists('print_sheets');
    }
};
