<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        return [
            'patient_id'       => Patient::factory(),
            'appointment_date' => now()->addDays(3)->toDateString(),
            'appointment_time' => '09:00:00',
            'purpose'          => 'Check-up',
            'status'           => 'pending',
        ];
    }

    public function status(string $status): static
    {
        return $this->state(['status' => $status]);
    }
}
