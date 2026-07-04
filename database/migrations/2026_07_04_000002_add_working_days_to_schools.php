<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-school working days. Ethiopian schools differ: some run Mon–Fri, most
 * run Mon–Sat, a few have other patterns. Stored as a JSON array of ISO
 * weekday numbers (1=Mon … 7=Sun). NULL means "not set" and the app falls
 * back to Mon–Sat, preserving the previous hardcoded behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('schools') && ! Schema::hasColumn('schools', 'working_days')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->json('working_days')->nullable()->after('academic_year');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('schools', 'working_days')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->dropColumn('working_days');
            });
        }
    }
};
