<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'planner_id' => User::factory()->planner(),
            'client_id' => User::factory(),
            'client_name' => fake()->firstName().' & '.fake()->firstName().' '.fake()->lastName(),
            'contact_number' => '+63917'.fake()->numerify('#######'),
            'event_date' => fake()->dateTimeBetween('+1 month', '+10 months')->format('Y-m-d'),
            'venue' => fake()->randomElement(['Casa Gorordo Museum', 'Marco Polo Plaza Cebu', 'Shangri-La Mactan', 'Radisson Blu Cebu', 'Sacred Heart Parish']),
            'package' => fake()->randomElement(['Classic Elegance', 'Garden Romance', 'Grand Ballroom']),
            'total_amount' => fake()->randomElement([85000, 120000, 150000, 220000]),
            'status' => 'pending',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => 'confirmed'])
            ->afterCreating(fn (Booking $booking) => $booking->ensureQrToken());
    }
}
