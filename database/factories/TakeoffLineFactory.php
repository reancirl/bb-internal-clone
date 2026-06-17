<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TakeoffLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TakeoffLine>
 */
class TakeoffLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'category' => fake()->randomElement(['CONCRETE', 'FRAMING', 'ROOFING', 'DRYWALL']),
            'item' => fake()->words(2, true),
            'formula' => 'ext_wall_lft * 2 / 16',
            'unit' => 'EA',
            'waste_pct' => fake()->randomElement([0, 15, 20, 25]),
            'ordered' => false,
            'on_site' => false,
        ];
    }
}
