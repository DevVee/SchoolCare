<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            MedicineCategorySeeder::class,
            AppointmentTimeSlotSeeder::class,
            SettingsSeeder::class,
        ]);
    }
}
