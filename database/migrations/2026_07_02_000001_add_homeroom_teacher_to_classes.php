<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Homeroom is a CLASS-level assignment ("Ms Almaz is homeroom of 2C"), not a
 * bare flag on the teacher. users.is_homeroom stays as a derived convenience
 * flag (kept in sync by TeacherAssignmentService) so old code keeps working.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('classes', 'homeroom_teacher_id')) {
            Schema::table('classes', function (Blueprint $table) {
                $table->foreignId('homeroom_teacher_id')
                    ->nullable()
                    ->after('level_order')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('classes', 'homeroom_teacher_id')) {
            Schema::table('classes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('homeroom_teacher_id');
            });
        }
    }
};
