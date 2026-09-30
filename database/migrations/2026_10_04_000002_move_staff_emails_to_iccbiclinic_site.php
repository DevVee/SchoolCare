<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff accounts were created on the placeholder @clinovia.app domain. Move them
 * to the clinic's own domain (admin@clinovia.app -> admin@iccbiclinic.site).
 * An account is skipped when its new address is already taken.
 */
return new class extends Migration
{
    private const OLD_DOMAIN = '@clinovia.app';

    private const NEW_DOMAIN = '@iccbiclinic.site';

    public function up(): void
    {
        $this->move(self::OLD_DOMAIN, self::NEW_DOMAIN);
    }

    public function down(): void
    {
        $this->move(self::NEW_DOMAIN, self::OLD_DOMAIN);
    }

    private function move(string $from, string $to): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $users = DB::table('users')->where('email', 'like', '%'.$from)->get(['id', 'email']);

        foreach ($users as $user) {
            $email = substr($user->email, 0, -strlen($from)).$to;

            if (DB::table('users')->where('email', $email)->exists()) {
                continue;
            }

            DB::table('users')->where('id', $user->id)->update(['email' => $email, 'updated_at' => now()]);
        }
    }
};
