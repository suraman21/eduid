<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'pin_hash')) {
            return; // self-heal: already applied by a partial deploy
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_hash', 255)->nullable()->after('password');
            $table->unsignedTinyInteger('pin_failed_count')->default(0)->after('pin_hash');
            $table->timestamp('pin_locked_until')->nullable()->after('pin_failed_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pin_hash', 'pin_failed_count', 'pin_locked_until']);
        });
    }
};
