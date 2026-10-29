<?php

namespace Database\Factories;

use App\Models\Inspector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inspector>
 */
class InspectorFactory extends Factory
{
    protected $model = Inspector::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->name(),
            'position' => $this->faker->jobTitle(),
            'office' => 'Provincial General Services Office',
            'office_id' => 'hris-12',
            'office_code' => 'PGSO',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
