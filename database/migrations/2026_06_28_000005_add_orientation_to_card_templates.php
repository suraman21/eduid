<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_templates', function (Blueprint $table) {
            // 'landscape' | 'portrait'; null = legacy (pre-fixed-ratio) template.
            $table->string('orientation', 20)->nullable()->after('card_height_mm');
        });
    }

    public function down(): void
    {
        Schema::table('card_templates', function (Blueprint $table) {
            $table->dropColumn('orientation');
        });
    }
};
