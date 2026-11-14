<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issued transcripts.
 *
 * A transcript is a formal document. Once a school hands one to a student or
 * another institution it must not silently change because a teacher later
 * edited a mark. So issuing stores an immutable JSON snapshot of exactly what
 * was printed, alongside a permanent serial the school can be asked about.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transcripts')) {
            return;
        }

        Schema::create('transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            $table->string('reference', 30);                 // "TR-AIA-0001"
            $table->enum('purpose', [
                'records', 'transfer', 'graduation', 'employment', 'further_study', 'other',
            ])->default('records');

            $table->date('issued_on');
            $table->json('snapshot');                        // exactly what was printed
            $table->string('note', 500)->nullable();

            $table->foreignId('issued_by')->nullable()
                ->constrained('users')->nullOnDelete();

            // A transcript can be revoked (issued in error, student disputes a
            // mark). The row is kept; it is flagged rather than deleted.
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 500)->nullable();

            $table->timestamps();

            $table->unique(['school_id', 'reference']);
            $table->index(['school_id', 'student_id'], 'transcripts_student_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcripts');
    }
};
