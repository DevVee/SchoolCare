<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'patient_number' => now()->year . '-' . str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'category'       => fake()->randomElement(Patient::categories()),
            'first_name'     => fake()->firstName(),
            'middle_name'    => null,
            'last_name'      => fake()->lastName(),
            'sex'            => fake()->randomElement(['male', 'female']),
            'birthdate'      => fake()->dateTimeBetween('-40 years', '-6 years')->format('Y-m-d'),
            'contact_number' => '09' . fake()->numerify('#########'),
            'is_active'      => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
