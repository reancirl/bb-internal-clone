<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'type' => fake()->randomElement(Vendor::TYPES),
            'location' => fake()->randomElement(['Sheridan', 'Buffalo', 'Gillette', 'Casper']),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->boolean(40) ? fake()->companyEmail() : null,
            'url' => fake()->boolean(30) ? fake()->url() : null,
            'notes' => fake()->boolean(30) ? fake()->sentence() : null,
        ];
    }
}
