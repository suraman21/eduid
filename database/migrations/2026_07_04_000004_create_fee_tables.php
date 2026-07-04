<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Student fee management (PRD gap: tuition/fees, distinct from the platform
 * subscription and the card-printing fee).
 *
 *   fee_structures  — the school's catalogue of charges (tuition, registration…)
 *   student_invoices — a specific charge owed by ONE student (amount + due date)
 *   fee_payments    — money received against an invoice (supports part-payment)
 *
 * Balance is never stored: it is always amount_etb − Σ payments, so it can
 * never drift out of sync with the payment ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fee_structures')) {
            Schema::create('fee_structures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->string('name', 120);                       // e.g. "Grade 1 Tuition"
                $table->decimal('amount_etb', 10, 2);
                $table->enum('frequency', ['one_time', 'monthly', 'term', 'annual'])->default('one_time');
                // Who it applies to when generating invoices in bulk.
                $table->enum('applies_to', ['all', 'class'])->default('all');
                $table->foreignId('target_class_id')->nullable()->constrained('classes')->nullOnDelete();
                $table->string('academic_year', 20)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['school_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('student_invoices')) {
            Schema::create('student_invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->foreignId('fee_structure_id')->nullable()->constrained('fee_structures')->nullOnDelete();
                $table->string('title', 150);
                $table->decimal('amount_etb', 10, 2);
                $table->date('due_date')->nullable();
                $table->string('academic_year', 20)->nullable();
                // unpaid → partial → paid, plus waived (forgiven) & void (cancelled).
                $table->enum('status', ['unpaid', 'partial', 'paid', 'waived', 'void'])->default('unpaid');
                $table->string('note', 255)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['school_id', 'status']);
                $table->index(['student_id', 'status']);
            });
        }

        if (! Schema::hasTable('fee_payments')) {
            Schema::create('fee_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('student_invoice_id')->constrained('student_invoices')->cascadeOnDelete();
                $table->decimal('amount_etb', 10, 2);
                $table->enum('method', ['cash', 'bank_transfer', 'telebirr', 'other'])->default('cash');
                $table->string('reference', 120)->nullable();
                $table->date('payment_date');
                $table->string('note', 255)->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['school_id', 'payment_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
        Schema::dropIfExists('student_invoices');
        Schema::dropIfExists('fee_structures');
    }
};
