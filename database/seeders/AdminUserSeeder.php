<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Bootstrap the first administrator account.
     *
     * Idempotent: does nothing once any administrator exists, so it is safe to
     * run on every boot. Credentials come from ADMIN_EMAIL / ADMIN_PASSWORD.
     * When ADMIN_PASSWORD is not set, a random password is generated and
     * printed once to the console/deploy log.
     */
    public function run(): void
    {
        if (User::role('administrator')->exists()) {
            return;
        }

        $email    = env('ADMIN_EMAIL') ?: 'admin@schoolcare.online';
        $password = env('ADMIN_PASSWORD') ?: Str::password(16, symbols: false);

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name'              => env('ADMIN_NAME', 'System Administrator'),
                'password'          => Hash::make($password),
                'is_active'         => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles('administrator');

        $this->command?->info("Administrator account created: {$email}");
        if (! env('ADMIN_PASSWORD')) {
            $this->command?->warn("Generated password (shown once — change it after first login): {$password}");
        }
    }
}
