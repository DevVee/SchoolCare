<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time slots become manageable from Admin: a label, an end time and the
 * weekdays the slot is offered on. slot_time stays the start time (it is what
 * appointments.appointment_time stores) and max_appointments stays the capacity.
 *
 *  - weekdays: JSON list of ISO weekday numbers (1 = Monday ... 7 = Sunday);
 *    NULL means every day the clinic is open.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_time_slots', function (Blueprint $table) {
            if (! Schema::hasColumn('appointment_time_slots', 'label')) {
                $table->string('label', 60)->nullable()->after('id');
            }
            if (! Schema::hasColumn('appointment_time_slots', 'end_time')) {
                $table->time('end_time')->nullable()->after('slot_time');
            }
            if (! Schema::hasColumn('appointment_time_slots', 'weekdays')) {
                $table->json('weekdays')->nullable()->after('max_appointments');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointment_time_slots', function (Blueprint $table) {
            $table->dropColumn(['label', 'end_time', 'weekdays']);
        });
    }
};
