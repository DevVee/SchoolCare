<?php

namespace Database\Factories;

use App\Models\Consultation;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consultation>
 */
class ConsultationFactory extends Factory
{
    protected $model = Consultation::class;

    public function definition(): array
    {
        return [
            'patient_id'      => Patient::factory(),
            'nurse_id'        => User::factory(),
            'visit_date'      => now()->toDateString(),
            'visit_time'      => '09:30',
            'chief_complaint' => fake()->randomElement(['Headache', 'Fever', 'Stomach ache', 'Cough']),
        ];
    }
}
