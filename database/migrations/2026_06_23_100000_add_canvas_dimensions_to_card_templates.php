<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_templates', function (Blueprint $table) {
            // Pixel dimensions of the uploaded background image — drives the preview canvas
            // and GD render size. Defaults to CR80 @ 300 DPI landscape (1012 × 638).
            $table->unsignedInteger('canvas_w')->default(1012)->after('back_image_path');
            $table->unsignedInteger('canvas_h')->default(638)->after('canvas_w');
        });
    }

    public function down(): void
    {
        Schema::table('card_templates', function (Blueprint $table) {
            $table->dropColumn(['canvas_w', 'canvas_h']);
        });
    }
};
