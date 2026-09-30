<?php

namespace Database\Seeders;

use App\Models\AppointmentTimeSlot;
use Illuminate\Database\Seeder;

class AppointmentTimeSlotSeeder extends Seeder
{
    /**
     * Default 30-minute slots from 7:00 AM to 5:00 PM, created ONLY when the
     * table is empty. Slots are managed in Admin → Appointment Slots, so a
     * deploy must never bring back a slot an administrator deleted.
     */
    public function run(): void
    {
        if (AppointmentTimeSlot::query()->exists()) {
            $this->command?->info('Appointment time slots already configured, skipped.');

            return;
        }

        $minutes = max(5, (int) settings('appointment_slot_minutes', 30));
        $start   = strtotime('07:00');
        $end     = strtotime('17:00');

        for ($time = $start; $time <= $end; $time += 1800) {
            AppointmentTimeSlot::firstOrCreate(
                ['slot_time' => date('H:i:s', $time)],
                ['end_time' => date('H:i:s', $time + $minutes * 60), 'max_appointments' => 5, 'is_active' => true]
            );
        }

        $this->command?->info('Appointment time slots seeded (07:00 to 17:00, every 30 min).');
    }
}
