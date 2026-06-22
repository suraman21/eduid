<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add composite index on (school_id, status) — the primary query
        // pattern for seat-count checks and student list filtering.
        Schema::table('students', function (Blueprint $table) {
            if (!$this->hasIndex('students', 'students_school_status_idx')) {
                $table->index(['school_id', 'status'], 'students_school_status_idx');
            }
        });

        // Add three-column index for teacher limit queries which filter on
        // (school_id, role, is_active) — the two-column index already exists
        // but MySQL must do a post-filter on is_active without the third column.
        Schema::table('users', function (Blueprint $table) {
            if (!$this->hasIndex('users', 'users_school_role_active_idx')) {
                $table->index(['school_id', 'role', 'is_active'], 'users_school_role_active_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_school_status_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_school_role_active_idx');
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn($idx) => ($idx['name'] ?? null) === $indexName);
    }
};
