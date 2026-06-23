<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add field_type to distinguish text / image / qr rendering.
        // Use string (not enum) because SQLite cannot add enum columns in ALTER.
        if (Schema::hasTable('card_template_fields') && ! Schema::hasColumn('card_template_fields', 'field_type')) {
            Schema::table('card_template_fields', function (Blueprint $table) {
                $table->string('field_type', 10)->default('text')->after('template_id');
            });

            // Back-fill from field_key for any existing rows.
            DB::table('card_template_fields')->get()->each(function ($row) {
                DB::table('card_template_fields')->where('id', $row->id)->update([
                    'field_type' => match ($row->field_key) {
                        'photo'   => 'image',
                        'qr_code' => 'qr',
                        default   => 'text',
                    },
                ]);
            });
        }

        // Add template version counter.
        if (Schema::hasTable('card_templates') && ! Schema::hasColumn('card_templates', 'version')) {
            Schema::table('card_templates', function (Blueprint $table) {
                $table->unsignedInteger('version')->default(1)->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('card_template_fields', 'field_type')) {
            Schema::table('card_template_fields', function (Blueprint $table) {
                $table->dropColumn('field_type');
            });
        }
        if (Schema::hasColumn('card_templates', 'version')) {
            Schema::table('card_templates', function (Blueprint $table) {
                $table->dropColumn('version');
            });
        }
    }
};
