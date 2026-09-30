<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMS delivery tracking:
 *  - status becomes a plain string so 'skipped' (master switch off / event
 *    disabled / no number) can be recorded alongside pending|sent|failed
 *  - event: which workflow sent it (appointment_approved, clinic_log, manual…)
 *  - provider_message_id: Semaphore message_id
 *  - attempts: delivery attempts made by SendSmsJob
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->change();
        });

        Schema::table('sms_logs', function (Blueprint $table) {
            $table->string('event', 50)->nullable()->after('message');
            $table->string('provider_message_id', 100)->nullable()->after('api_response');
            $table->unsignedTinyInteger('attempts')->default(0)->after('provider_message_id');
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropIndex(['event']);
            $table->dropColumn(['event', 'provider_message_id', 'attempts']);
        });
    }
};
