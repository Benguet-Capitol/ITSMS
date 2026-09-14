<?php

namespace Database\Factories;

use App\Models\TicketComplexityLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketComplexityLevel>
 */
class TicketComplexityLevelFactory extends Factory
{
    protected $model = TicketComplexityLevel::class;

    public function definition(): array
    {
        return [
            'label' => $this->faker->unique()->word(),
            'description' => $this->faker->sentence(),
            'examples' => $this->faker->sentence(),
            'min_minutes' => 0,
            'max_minutes' => 30,
            'color' => 'info',
            'sort_order' => 0,
        ];
    }
}
