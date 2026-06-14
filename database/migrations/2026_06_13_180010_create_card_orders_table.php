<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('order_code', 30)->unique();                    // "ORD-AIA-2026-0847"
            $table->foreignId('ordered_by')->constrained('users')->cascadeOnDelete();
            $table->json('student_ids');                                    // Array of student IDs
            $table->integer('student_count');
            $table->enum('order_type', ['first_batch', 'annual_renewal', 'replacement', 'new_student']);
            $table->enum('status', ['pending', 'confirmed', 'printing', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->text('cancel_reason')->nullable();                     // If cancelled, why
            $table->string('print_file_path', 500)->nullable();            // Server-side only, never exposed
            $table->decimal('amount_etb', 10, 2)->nullable();              // Price charged
            $table->text('tracking_note')->nullable();                     // "Shipped via X, expected Y"
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_orders');
    }
};
