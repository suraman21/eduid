<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('feature_registry')) {
            Schema::create('feature_registry', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->string('label', 200);
                $table->text('description')->nullable();
                $table->string('category', 50)->default('core');
                $table->boolean('is_default_enabled')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('plan_features')) {
            Schema::create('plan_features', function (Blueprint $table) {
                $table->id();
                $table->enum('plan_tier', ['starter','standard','premium']);
                $table->string('feature_key', 100);
                $table->foreign('feature_key')->references('key')->on('feature_registry')->cascadeOnDelete();
                $table->unique(['plan_tier','feature_key']);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('school_feature_overrides')) {
            Schema::create('school_feature_overrides', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->string('feature_key', 100);
                $table->foreign('feature_key')->references('key')->on('feature_registry')->cascadeOnDelete();
                $table->boolean('is_enabled');
                $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('overridden_at')->nullable();
                $table->unique(['school_id','feature_key']);
                $table->timestamps();
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('school_feature_overrides');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('feature_registry');
    }
};
