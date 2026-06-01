<?php

namespace Database\Factories;

use App\Models\LaborRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaborRate>
 */
class LaborRateFactory extends Factory
{
    public function definition(): array
    {
        $base = fake()->randomFloat(2, 20, 40);

        return [
            'class_name' => fake()->unique()->jobTitle(),
            'base_rate' => $base,
            'burden_rate' => round($base * 1.32, 2),
            'bill_rate' => round($base * 2.2, 2),
            'total' => round($base * 2.5, 2),
            'notes' => null,
        ];
    }
}
