<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientLog>
 */
class PatientLogFactory extends Factory
{
    protected $model = PatientLog::class;

    public function definition(): array
    {
        return [
            'patient_id'      => Patient::factory(),
            'logged_by'       => User::factory(),
            'log_date'        => now()->toDateString(),
            'time_in'         => '08:15',
            'chief_complaint' => fake()->randomElement(['Headache', 'Fever', 'Stomach ache', 'Cough']),
            'disposition'     => 'returned_to_class',
        ];
    }
}
