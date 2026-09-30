<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled doctor / dentist clinic days (SSCMS specialist_visits).
 * type is one of settings('specialist_types').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialist_visits', function (Blueprint $table) {
            $table->id();
            $table->string('type', 60);
            $table->string('specialist_name', 150);
            $table->date('visit_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('capacity')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('scheduled'); // scheduled | completed | cancelled
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['visit_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialist_visits');
    }
};
