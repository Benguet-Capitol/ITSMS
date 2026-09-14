<?php

namespace Database\Factories;

use App\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agency>
 */
class AgencyFactory extends Factory
{
    protected $model = Agency::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company(),
            'abbreviation' => strtoupper($this->faker->unique()->lexify('???')),
        ];
    }
}
