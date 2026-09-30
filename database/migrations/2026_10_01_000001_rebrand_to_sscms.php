<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rebrand stored settings from "Clinovia" to SSCMS.
 *
 * Only values that still hold the old built-in defaults are changed, so any
 * name an administrator already typed in is kept as-is.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $replace = [
            'app_name'           => ['Clinovia' => 'SSCMS'],
            'app_short_name'     => ['Clinovia' => 'SSCMS'],
            'org_name'           => ['Clinovia' => ''],
            'org_short_name'     => ['Clinovia' => ''],
            'login_headline'     => ["Your clinic,\ncompletely\norganized." => 'Welcome back.'],
            'login_subtext'      => ['Patient records, appointments, medicines and AI in one platform for your entire school clinic.' => 'Sign in to manage patient records, clinic visits, appointments and medicine inventory.'],
            'login_quote'        => ['Clinovia cut our record-keeping time in half, and Cobi AI is genuinely useful for the entire clinic staff.' => ''],
            'login_quote_author' => ['School Clinic Nurse, Metro Manila' => ''],
            'sms_sender_name'    => ['CLINOVIA' => env('SEMAPHORE_SENDER_NAME') ?: 'CLINOVIA'],
        ];

        foreach ($replace as $key => $map) {
            foreach ($map as $old => $new) {
                DB::table('settings')->where('key', $key)->where('value', $old)->update(['value' => $new]);
            }
        }

        // Settings are cached; make the new values visible immediately.
        try {
            if (function_exists('settings')) {
                settings()->flush();
            }
        } catch (\Throwable) {
            // cache store may not exist yet on a fresh install
        }
    }

    public function down(): void
    {
        // Branding values are user data from here on; nothing to restore.
    }
};
