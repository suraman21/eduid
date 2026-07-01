<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Printer calibration profiles (print module spec — Duplex Calibration).
 * Every physical printer feeds paper slightly differently; a profile stores the
 * measured front/back offsets so generated PDFs compensate automatically.
 * Global super-admin infrastructure — not tenant-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printer_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();          // e.g. "Epson L3250"
            $table->decimal('x_offset_front_mm', 6, 2)->default(0);
            $table->decimal('y_offset_front_mm', 6, 2)->default(0);
            $table->decimal('x_offset_back_mm', 6, 2)->default(0);
            $table->decimal('y_offset_back_mm', 6, 2)->default(0);
            $table->decimal('rotation_offset_deg', 5, 2)->default(0);
            $table->boolean('mirror_back')->default(false); // mirror back horizontally
            // How the operator flips the sheet between passes:
            // 'long_edge'  → flip like turning a BOOK page
            // 'short_edge' → flip like turning a CALENDAR page
            $table->string('flip_mode', 20)->default('long_edge');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printer_profiles');
    }
};
