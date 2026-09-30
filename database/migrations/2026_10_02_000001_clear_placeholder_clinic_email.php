<?php

use App\Services\SettingsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The clinic email used to default to a placeholder address (clinic@clinovia.app).
 * Clear it, but only where it was never changed by an administrator.
 */
return new class extends Migration
{
    private const PLACEHOLDER = 'clinic@clinovia.app';

    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $updated = DB::table('settings')
            ->where('key', 'clinic_email')
            ->where('value', self::PLACEHOLDER)
            ->update(['value' => '', 'updated_at' => now()]);

        if ($updated) {
            Cache::forget(SettingsService::CACHE_KEY);
        }
    }

    public function down(): void
    {
        // Not reversible: an empty clinic email is a valid admin choice.
    }
};
