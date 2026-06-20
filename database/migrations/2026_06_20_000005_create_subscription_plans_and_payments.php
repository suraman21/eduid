<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('subscription_plans')) {
            Schema::create('subscription_plans', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->enum('tier', ['starter','standard','premium']);
                $table->decimal('price_monthly_etb', 10, 2);
                $table->decimal('price_annual_etb', 10, 2)->nullable();
                $table->integer('max_students');
                $table->integer('max_teachers');
                $table->integer('max_school_admins')->default(1);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
                $table->decimal('amount_etb', 10, 2);
                $table->enum('payment_method', ['bank_transfer','cash','chapa','other'])->default('bank_transfer');
                $table->string('bank_name', 100)->nullable();
                $table->string('reference_number', 200)->nullable();
                $table->enum('status', ['pending','confirmed','failed','refunded'])->default('pending');
                $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
                $table->date('payment_date');
                $table->integer('period_months')->default(1);
                $table->text('notes')->nullable();
                $table->string('receipt_path', 500)->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['school_id','status']);
                $table->index(['school_id','payment_date']);
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscription_plans');
    }
};
