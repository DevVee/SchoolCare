<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The old login copy was stored in several spellings (with em dashes), so the
 * exact-match rebrand migration missed some installs. Match on distinctive
 * phrases instead; values an administrator wrote themselves are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $set = fn (string $key, string $like, string $value) => DB::table('settings')
            ->where('key', $key)->where('value', 'like', $like)->update(['value' => $value]);

        $set('login_quote', '%Clinovia%', '');
        $set('login_quote', '%Cobi AI is genuinely useful%', '');
        $set('login_quote_author', 'School Clinic Nurse%', '');
        $set('login_subtext', '%one platform for your entire school clinic%',
            'Sign in to manage patient records, clinic visits, appointments and medicine inventory.');
        $set('login_headline', '%completely%organized%', 'Welcome back.');

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
