<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => User::factory(),
            'planner_id' => User::factory()->planner(),
            'scheduled_at' => now()->addDays(fake()->numberBetween(2, 30))->setTime(fake()->numberBetween(9, 17), 0),
            'mode' => fake()->randomElement(array_keys(Meeting::MODES)),
            'location' => 'FMT Office, Cebu City',
            'agenda' => 'Discuss the wedding theme and package.',
            'status' => 'requested',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => 'confirmed', 'responded_at' => now()]);
    }
}
