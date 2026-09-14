<?php

namespace Database\Factories;

use App\Models\ItemType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemType>
 */
class ItemTypeFactory extends Factory
{
    protected $model = ItemType::class;

    public function definition(): array
    {
        return [
            'classification' => $this->faker->word(),
            'purpose' => $this->faker->word(),
            'type' => $this->faker->unique()->words(2, true),
            'is_main_inventory' => false,
            'is_component' => false,
            'supports_internal_components' => false,
            'part_number' => $this->faker->bothify('PN-####'),
            'status' => 'active',
        ];
    }
}
