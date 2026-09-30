<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tokens for the "invites" password broker (config/auth.php). Kept apart from
 * password_reset_tokens so a 60-minute reset link can never be redeemed as a
 * 3-day invitation, or the other way round.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_invitation_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invitation_tokens');
    }
};
