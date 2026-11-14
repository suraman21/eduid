<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff document vault.
 *
 * Same private-disk model as student documents, but staff paperwork is a
 * different animal: contracts, teaching licences, police clearances and work
 * permits all have expiry dates that the school is legally obliged to watch.
 * That is why `expires_on` is indexed here — the register is built around it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staff_documents')) {
            return;
        }

        Schema::create('staff_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->enum('type', [
                'contract', 'teaching_licence', 'police_clearance',
                'medical_certificate', 'qualification', 'national_id',
                'work_permit', 'reference_letter', 'other',
            ])->default('other');

            $table->string('title', 150);
            $table->text('description')->nullable();

            $table->string('file_path', 500);
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable();

            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();

            // A licence the school never checked on is not the same as one it
            // verified against the original.
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'user_id'], 'staff_docs_user_idx');
            $table->index(['school_id', 'type'], 'staff_docs_type_idx');
            $table->index(['school_id', 'expires_on'], 'staff_docs_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_documents');
    }
};
