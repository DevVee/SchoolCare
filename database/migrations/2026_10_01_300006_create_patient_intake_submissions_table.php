<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public Student Health Information Form submissions.
 *
 * The public form never writes to `patients`. Each submission waits here as
 * `pending` until staff approve it (as a new patient, or merged into an
 * existing one) or reject it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_intake_submissions', function (Blueprint $table) {
            $table->id();
            $table->json('payload');
            $table->string('student_id', 50)->nullable();
            $table->string('last_name', 100);
            $table->string('first_name', 100);
            $table->date('birthdate')->nullable();
            $table->string('contact', 30)->nullable();
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('matched_patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->string('submitted_ip', 45)->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_intake_submissions');
    }
};
