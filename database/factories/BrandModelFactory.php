<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandModel;
use App\Models\ItemType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrandModel>
 */
class BrandModelFactory extends Factory
{
    protected $model = BrandModel::class;

    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'item_type_id' => ItemType::factory(),
            'name' => $this->faker->words(2, true),
            'specification' => $this->faker->words(3, true),
            'status' => 'active',
        ];
    }
}
