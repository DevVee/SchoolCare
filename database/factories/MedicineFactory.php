<?php

namespace Database\Factories;

use App\Models\Medicine;
use App\Models\MedicineCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Medicine>
 */
class MedicineFactory extends Factory
{
    protected $model = Medicine::class;

    public function definition(): array
    {
        return [
            'name'                => ucfirst(fake()->unique()->word()) . ' ' . fake()->randomElement(['500mg', '250mg', '10mg']),
            'category_id'         => MedicineCategory::factory(),
            'quantity'            => 100,
            'unit'                => 'tablet',
            'expiration_date'     => now()->addYear()->toDateString(),
            'low_stock_threshold' => 10,
            'is_active'           => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(['expiration_date' => now()->subDay()->toDateString()]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
