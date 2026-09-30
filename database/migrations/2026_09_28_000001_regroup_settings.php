<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Moves existing settings rows into the groups defined in config/settings.php.
 * Values are never touched, except `medicine_units`: its seeded value (plural
 * units) was never read by the app, while the medicine form used singular
 * units — so if it still holds the untouched seed value it is replaced with
 * the list the form actually used, keeping existing medicine records valid.
 * Missing keys are inserted by SettingsSeeder (SettingsService::syncDefaults).
 */
return new class extends Migration
{
    private array $moves = [
        'max_daily_appointments'   => 'appointments',
        'low_stock_threshold'      => 'inventory',
        'expiry_warning_days'      => 'inventory',
        'sms_enabled'              => 'notifications',
        'sms_log_guardian_enabled' => 'notifications',
        'patient_categories'       => 'clinic',
        'medicine_units'           => 'clinic',
    ];

    private string $oldUnits = '["tablets","capsules","ml","vials","sachets","ampules","suppositories","drops","patches","other"]';

    private string $formUnits = '["tablet","capsule","ml","vial","piece","box","bottle","sachet","other"]';

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('settings')) {
            return;
        }

        foreach ($this->moves as $key => $group) {
            DB::table('settings')->where('key', $key)->update(['group' => $group]);
        }

        DB::table('settings')
            ->where('key', 'medicine_units')
            ->where('value', $this->oldUnits)
            ->update(['value' => $this->formUnits]);

        foreach (['settings.values.v2', 'sscms_settings_all'] as $cacheKey) {
            try {
                cache()->forget($cacheKey);
            } catch (\Throwable) {
            }
        }
    }

    public function down(): void
    {
        $old = [
            'max_daily_appointments'   => 'notifications',
            'low_stock_threshold'      => 'notifications',
            'expiry_warning_days'      => 'notifications',
            'sms_enabled'              => 'sms',
            'sms_log_guardian_enabled' => 'sms',
        ];

        foreach ($old as $key => $group) {
            DB::table('settings')->where('key', $key)->update(['group' => $group]);
        }
    }
};
