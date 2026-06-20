<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('card_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->cascadeOnDelete();
            $table->string('name', 200);
            $table->enum('template_type', ['student','employee'])->default('student');
            $table->enum('scope', ['global','school'])->default('school');
            $table->string('front_image_path', 500);
            $table->string('back_image_path', 500)->nullable();
            $table->decimal('card_width_mm', 5, 2)->default(85.60);
            $table->decimal('card_height_mm', 5, 2)->default(54.00);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('card_template_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('card_templates')->cascadeOnDelete();
            $table->string('field_key', 50);
            $table->string('label', 100)->nullable();
            $table->decimal('x_mm', 6, 2);
            $table->decimal('y_mm', 6, 2);
            $table->decimal('width_mm', 6, 2)->nullable();
            $table->decimal('height_mm', 6, 2)->nullable();
            $table->smallInteger('font_size')->default(10);
            $table->string('font_color', 7)->default('#000000');
            $table->string('font_family', 50)->default('inter');
            $table->enum('text_align', ['left','center','right'])->default('left');
            $table->enum('font_weight', ['normal','bold'])->default('normal');
            $table->smallInteger('z_index')->default(0);
            $table->text('custom_value')->nullable();
            $table->timestamps();
        });

        // Alter card_orders to add template_id FK
        Schema::table('card_orders', function (Blueprint $table) {
            $table->foreignId('template_id')->nullable()->after('id')->constrained('card_templates')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('card_orders', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropColumn('template_id');
        });
        Schema::dropIfExists('card_template_fields');
        Schema::dropIfExists('card_templates');
    }
};
