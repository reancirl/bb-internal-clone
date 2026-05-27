<?php

namespace Database\Factories;

use App\Models\TimeCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeCard>
 */
class TimeCardFactory extends Factory
{
    public function definition(): array
    {
        $clockIn = fake()->dateTimeBetween('-7 days', 'now');
        $clockOut = (clone $clockIn)->modify('+'.fake()->numberBetween(60, 480).' minutes');

        return [
            'user_id' => User::factory(),
            'clock_in_at' => $clockIn,
            'clock_out_at' => $clockOut,
            'notes' => fake()->boolean(30) ? fake()->sentence() : null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['clock_out_at' => null]);
    }
}
