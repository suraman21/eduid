<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('school_name', 200);
            $table->string('phone', 20);
            $table->string('email', 150)->nullable();
            $table->text('message')->nullable();
            $table->string('preferred_plan', 20)->nullable();       // starter, standard, premium
            $table->enum('status', ['new', 'contacted', 'demo_scheduled', 'converted', 'lost'])->default('new');
            $table->text('admin_notes')->nullable();                // Super Admin internal notes
            $table->timestamp('contacted_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
