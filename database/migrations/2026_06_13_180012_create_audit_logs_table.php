<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();  // NULL for super_admin actions
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 50);                                  // 'student.create', 'order.confirm', etc.
            $table->string('target_type', 50);                             // 'student', 'card_order', 'user', etc.
            $table->unsignedBigInteger('target_id')->nullable();           // ID of the affected record
            $table->json('old_values')->nullable();                        // Previous state (for updates)
            $table->json('new_values')->nullable();                        // New state (for creates/updates)
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['school_id', 'action']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
