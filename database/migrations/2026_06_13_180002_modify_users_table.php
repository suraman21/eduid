<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add school_id after id (nullable for super_admin)
            $table->foreignId('school_id')->nullable()->after('id')->constrained('schools')->nullOnDelete();

            // Role system. 'staff' is included here so that fresh installs
            // (including the SQLite test database) accept all four roles. On
            // already-deployed MySQL databases the later
            // alter_users_add_staff_columns migration widens the enum instead.
            $table->enum('role', ['super_admin', 'school_admin', 'teacher', 'staff'])->default('teacher')->after('name');

            // Additional profile fields
            $table->string('phone', 20)->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('phone');
            $table->timestamp('last_login_at')->nullable()->after('is_active');

            // Account lockout
            $table->integer('failed_login_count')->default(0)->after('last_login_at');
            $table->timestamp('locked_until')->nullable()->after('failed_login_count');

            // Indexes
            $table->index(['school_id', 'role']);
        });

        // Drop the default unique on email (we need unique per school instead)
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->unique(['email', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email', 'school_id']);
            $table->unique('email');

            $table->dropIndex(['school_id', 'role']);
            $table->dropForeign(['school_id']);
            $table->dropColumn([
                'school_id', 'role', 'phone', 'is_active',
                'last_login_at', 'failed_login_count', 'locked_until',
            ]);
        });
    }
};
