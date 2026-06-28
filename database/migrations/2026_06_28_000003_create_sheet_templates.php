<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sheet templates describe a physical A4 (or other) PVC card sheet: how many
 * card slots it holds and exactly where each slot sits, in millimetres. These
 * are GLOBAL Arkeon printing infrastructure (not per-school) — the super admin
 * manages them. Slot positions are derived from a regular grid (margins +
 * spacing) so templates stay configurable rather than hardcoded; an optional
 * positions_json can override the grid for irregular sheets later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sheet_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();          // e.g. 'a4-10up'
            $table->string('name', 100);                  // 'A4 PVC (10 Cards)'
            $table->decimal('sheet_w_mm', 7, 3);          // 210.000
            $table->decimal('sheet_h_mm', 7, 3);          // 297.000
            $table->unsignedTinyInteger('columns');       // 2
            $table->unsignedTinyInteger('rows');          // 5
            $table->decimal('card_w_mm', 7, 3);           // 85.600 (CR80)
            $table->decimal('card_h_mm', 7, 3);           // 53.980 (CR80)
            $table->decimal('margin_top_mm', 7, 3)->default(0);
            $table->decimal('margin_left_mm', 7, 3)->default(0);
            $table->decimal('h_spacing_mm', 7, 3)->default(0);
            $table->decimal('v_spacing_mm', 7, 3)->default(0);
            $table->json('positions_json')->nullable();   // optional irregular override
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sheet_templates');
    }
};
