<?php

use App\Services\SettingsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rebrand stored settings from SSCMS (and the older Clinovia default) to SchoolCare.
 *
 * Only values that still hold an old built-in default are changed, so any
 * name an administrator already typed in is kept as-is.
 */
return new class extends Migration
{
    private const OLD_DEFAULTS = ['SSCMS', 'Clinovia'];

    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $updated = DB::table('settings')
            ->whereIn('key', ['app_name', 'app_short_name'])
            ->whereIn('value', self::OLD_DEFAULTS)
            ->update(['value' => 'SchoolCare', 'updated_at' => now()]);

        if ($updated) {
            Cache::forget(SettingsService::CACHE_KEY);
        }
    }

    public function down(): void
    {
        // Branding values are user data from here on; nothing to restore.
    }
};
