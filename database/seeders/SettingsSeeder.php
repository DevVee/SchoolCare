<?php

namespace Database\Seeders;

use App\Services\SettingsService;
use Illuminate\Database\Seeder;

/**
 * Inserts any setting defined in config/settings.php that is missing from the
 * database. Existing values are never overwritten, so this is safe to run on
 * every deploy.
 */
class SettingsSeeder extends Seeder
{
    public function run(SettingsService $settings): void
    {
        $inserted = $settings->syncDefaults();

        $this->command?->info("Settings synced ({$inserted} new).");
    }
}
