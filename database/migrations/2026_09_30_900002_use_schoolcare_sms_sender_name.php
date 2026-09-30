<?php

use App\Services\SettingsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SMS sender name: the clinic registered "SchoolCare" with Semaphore and made it
 * the account default (owner, 2026-09-30). The saved sender name changes only when
 * it is still the old "ICCBICLINIC" or the unregistered "SCHOOLCARE" default, so a
 * name an admin chose on purpose is kept. Semaphore matches sender names exactly,
 * capitals included. With no saved row, one is added so the server .env value
 * (SEMAPHORE_SENDER_NAME) no longer decides it.
 */
return new class extends Migration
{
    private const OLD = ['ICCBICLINIC', 'SCHOOLCARE'];

    private const NEW = 'SchoolCare';

    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $row = DB::table('settings')->where('key', 'sms_sender_name')->first();

        if (! $row) {
            DB::table('settings')->insert([
                'key' => 'sms_sender_name',
                'value' => self::NEW,
                'type' => 'string',
                'group' => 'sms',
                'description' => 'SMS sender name (max 11 chars)',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } elseif (in_array($row->value, self::OLD, true)) {
            DB::table('settings')->where('id', $row->id)->update(['value' => self::NEW, 'updated_at' => now()]);
        } else {
            return;
        }

        Cache::forget(SettingsService::CACHE_KEY);
    }

    public function down(): void
    {
        $updated = DB::table('settings')
            ->where('key', 'sms_sender_name')
            ->where('value', self::NEW)
            ->update(['value' => 'ICCBICLINIC', 'updated_at' => now()]);

        if ($updated) {
            Cache::forget(SettingsService::CACHE_KEY);
        }
    }
};
