<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificates: transfer letters, completion certificates, testimonials and
 * bonafide letters generated from a student's own records.
 *
 * Like transcripts these are frozen on issue — a certificate that a family
 * carries to another country must not change afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            // CERT-AIA-0001 — per school, never reused.
            $table->string('reference', 40);
            $table->enum('type', [
                'transfer', 'completion', 'character', 'bonafide', 'custom',
            ]);

            // Who it is addressed to, e.g. "Ministry of Education, Kenya".
            $table->string('addressed_to', 150)->nullable();

            $table->date('issued_on');
            $table->json('snapshot');          // rendered letter, frozen
            $table->text('body_note')->nullable(); // the registrar's own paragraph
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 500)->nullable();

            $table->timestamps();

            $table->unique(['school_id', 'reference']);
            $table->index(['school_id', 'type']);
            $table->index(['school_id', 'issued_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
