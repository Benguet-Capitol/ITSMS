<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\ItemType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'item_type_id' => ItemType::factory(),
            'property_number' => $this->faker->unique()->bothify('PROP-#####'),
            'status' => 'active',
        ];
    }
}
