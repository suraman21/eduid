<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_template_fields', function (Blueprint $table) {
            $table->string('side', 10)->default('front')->after('z_index');
            $table->unsignedTinyInteger('border_radius')->default(0)->after('custom_value');
        });
    }

    public function down(): void
    {
        Schema::table('card_template_fields', function (Blueprint $table) {
            $table->dropColumn(['side', 'border_radius']);
        });
    }
};
