<?php

namespace Database\Factories;

use App\Models\MeasurementUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeasurementUnit>
 */
class MeasurementUnitFactory extends Factory
{
    protected $model = MeasurementUnit::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'abbreviation' => $this->faker->lexify('???'),
            'description' => $this->faker->sentence(),
        ];
    }
}
