<?php

namespace Database\Factories;

use App\Models\BrandModel;
use App\Models\ItSupply;
use App\Models\MeasurementUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItSupply>
 */
class ItSupplyFactory extends Factory
{
    protected $model = ItSupply::class;

    public function definition(): array
    {
        return [
            'brand_model_id' => BrandModel::factory(),
            'measurement_unit_id' => MeasurementUnit::factory(),
            'description' => $this->faker->sentence(),
            'quantity' => $this->faker->numberBetween(1, 50),
        ];
    }
}
