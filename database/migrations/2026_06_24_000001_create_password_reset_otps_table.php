<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * OTP-based password reset codes. Keyed by (school_id, email) because email
     * is only unique *within* a school in this multi-tenant system. The code is
     * stored hashed; a short expiry plus an attempt counter limit brute-forcing.
     */
    public function up(): void
    {
        Schema::create('password_reset_otps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('email');
            $table->string('otp_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['school_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_otps');
    }
};
