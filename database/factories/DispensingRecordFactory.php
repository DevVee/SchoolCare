<?php

namespace Database\Factories;

use App\Models\DispensingRecord;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DispensingRecord>
 */
class DispensingRecordFactory extends Factory
{
    protected $model = DispensingRecord::class;

    public function definition(): array
    {
        return [
            'patient_id'   => Patient::factory(),
            'medicine_id'  => Medicine::factory(),
            'quantity'     => 2,
            'dispensed_by' => User::factory(),
            'dispensed_at' => now(),
        ];
    }
}
