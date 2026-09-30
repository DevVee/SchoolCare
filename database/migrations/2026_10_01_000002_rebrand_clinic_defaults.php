<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-up to the SSCMS rebrand: the stored clinic name and SMS templates that
 * still hold the old "Clinovia" defaults. Admin-edited values are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->where('key', 'clinic_name')->where('value', 'Clinovia School Clinic')
            ->update(['value' => 'School Clinic']);

        DB::table('settings')->where('key', 'like', 'sms_template_%')->where('value', 'like', '% - Clinovia')
            ->get(['id', 'value'])
            ->each(fn ($row) => DB::table('settings')->where('id', $row->id)
                ->update(['value' => substr($row->value, 0, -strlen(' - Clinovia')).' - {clinic}']));

        if (function_exists('settings')) {
            try {
                settings()->flush();
            } catch (\Throwable) {
                // cache store not ready on a fresh install
            }
        }
    }

    public function down(): void
    {
        // Values are user data from here on.
    }
};
