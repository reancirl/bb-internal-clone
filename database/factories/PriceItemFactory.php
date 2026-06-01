<?php

namespace Database\Factories;

use App\Models\PriceCategory;
use App\Models\PriceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceItem>
 */
class PriceItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'price_category_id' => PriceCategory::factory(),
            'name' => fake()->words(3, true),
            'unit' => fake()->randomElement(PriceItem::UNITS),
            'fast_price' => fake()->boolean(60) ? fake()->randomFloat(2, 1, 300) : null,
            'material_cost' => fake()->boolean(50) ? fake()->randomFloat(2, 1, 250) : null,
            'bb_install_rate' => fake()->boolean(50) ? fake()->randomFloat(2, 1, 100) : null,
            'sub_install_rate' => fake()->boolean(40) ? fake()->randomFloat(2, 1, 100) : null,
            'preferred_vendor_id' => null,
            'notes' => fake()->boolean(30) ? fake()->sentence() : null,
        ];
    }
}
