<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Document vault.
 *
 * Files live on the private disk and are only ever served through an
 * authenticated, tenant-scoped route — never a public /storage URL, matching
 * how student photos and ID-card templates are already handled.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_documents')) {
            return;
        }

        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            $table->enum('type', [
                'birth_certificate', 'previous_report_card', 'photo_id',
                'medical_record', 'transfer_letter', 'guardian_id',
                'admission_letter', 'certificate', 'other',
            ])->default('other');

            $table->string('title', 150);
            $table->text('description')->nullable();

            $table->string('file_path', 500);                  // private disk path
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable();  // bytes

            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();

            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'student_id'], 'documents_student_idx');
            $table->index(['school_id', 'type'], 'documents_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_documents');
    }
};
