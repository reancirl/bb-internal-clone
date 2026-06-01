<?php

namespace Database\Factories;

use App\Models\PriceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceCategory>
 */
class PriceCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('##'),
            'name' => fake()->unique()->words(2, true),
            'sort' => fake()->numberBetween(1, 19),
        ];
    }
}
