<?php

namespace Database\Factories;

use App\Models\TradePartner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradePartner>
 */
class TradePartnerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'location' => fake()->randomElement(['Sheridan', 'Buffalo', 'Gillette', 'Casper', 'Mills', 'Rozet']),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->boolean(30) ? fake()->companyEmail() : null,
            'used_before' => fake()->boolean(25),
            'negotiated_price' => fake()->boolean(15) ? '$'.fake()->numberBetween(2, 20).'/SF' : null,
            'referral_source' => fake()->boolean(40) ? 'online' : null,
            'notes' => fake()->boolean(20) ? fake()->sentence() : null,
            'do_not_use' => false,
        ];
    }

    public function used(): static
    {
        return $this->state(fn () => ['used_before' => true]);
    }

    public function doNotUse(): static
    {
        return $this->state(fn () => ['do_not_use' => true]);
    }
}
