<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user onboarding tour state (welcome coach-marks + Getting Started
 * checklist). Stored on the user so progress follows them across devices.
 * Task completion is derived LIVE from real data — only the UI flags
 * (tour seen / dismissed / celebrated) live here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'onboarding_state')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('onboarding_state')->nullable()->after('pin_locked_until');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'onboarding_state')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('onboarding_state');
            });
        }
    }
};
