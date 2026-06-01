<?php

namespace Database\Factories;

use App\Models\EquipmentRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentRate>
 */
class EquipmentRateFactory extends Factory
{
    public function definition(): array
    {
        $day = fake()->randomFloat(2, 100, 400);

        return [
            'name' => fake()->unique()->words(2, true),
            'day_rate' => $day,
            'week_rate' => round($day * 4, 2),
            'month_rate' => round($day * 12, 2),
            'notes' => null,
        ];
    }
}
