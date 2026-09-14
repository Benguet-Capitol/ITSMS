<?php

namespace Database\Factories;

use App\Models\ItService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItService>
 */
class ItServiceFactory extends Factory
{
    protected $model = ItService::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->sentence(),
            'code' => strtoupper($this->faker->unique()->lexify('???')),
        ];
    }
}
