<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->streetName().' Residence',
            'client_name' => fake()->name(),
            'address' => fake()->address(),
            'status' => fake()->randomElement(Project::STATUSES),
            'house_sqft' => fake()->randomFloat(2, 800, 3000),
            'roof_sqft' => fake()->randomFloat(2, 1000, 3500),
            'ext_wall_lft' => fake()->randomFloat(2, 100, 300),
            'ext_wall_sqft' => fake()->randomFloat(2, 800, 2500),
            'int_wall_sqft' => fake()->randomFloat(2, 1500, 5000),
            'slab_depth' => 0.33,
        ];
    }
}
