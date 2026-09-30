<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - assets                    clinic equipment / supplies inventory (SSCMS assets)
 * - patient_log_attachments   injury photos attached to a clinic visit. Files
 *                             live on the private "local" disk and are only
 *                             served through an authorized route.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('category', 100)->nullable();
            $table->string('property_number', 100)->nullable(); // serial / property no.
            $table->unsignedInteger('quantity')->default(1);
            $table->string('condition', 60);
            $table->string('location', 150)->nullable();
            $table->date('acquired_at')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('image_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index(['condition', 'category']);
        });

        Schema::create('patient_log_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_log_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_log_attachments');
        Schema::dropIfExists('assets');
    }
};
