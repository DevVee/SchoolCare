<?php

namespace Database\Factories;

use App\Models\MedicineCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicineCategory>
 */
class MedicineCategoryFactory extends Factory
{
    protected $model = MedicineCategory::class;

    public function definition(): array
    {
        return [
            'name'        => fake()->unique()->words(2, true),
            'description' => null,
        ];
    }
}
