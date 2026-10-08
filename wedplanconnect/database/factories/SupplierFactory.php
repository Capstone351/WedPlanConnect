<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'category' => fake()->randomElement(Supplier::CATEGORIES),
            'phone' => '+63917'.fake()->numerify('#######'),
            'email' => fake()->companyEmail(),
            'description' => fake()->sentence(12),
            'starting_price' => fake()->randomElement([15000, 25000, 40000]),
            'availability' => 'available',
        ];
    }
}
