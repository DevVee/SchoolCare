<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actions the assistant proposes (send an SMS or email, book, move or cancel an
 * appointment, change a setting). Nothing runs until the user taps Confirm
 * (App\Services\Coco\CocoActions). Rows are kept after they are done, as a
 * record of what was proposed and what happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_pending_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->string('type', 40);
            $table->json('payload');
            $table->json('preview');
            $table->string('status', 20)->default('pending'); // pending | confirmed | cancelled | expired | failed
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_pending_actions');
    }
};
