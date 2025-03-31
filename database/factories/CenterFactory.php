<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Center>
 */
class CenterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'city_id' => rand(1, 5), // Assuming 5 cities are seeded
            'name' => [ 'en' => $this->faker->company()],
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'is_active' => true,
            'synced_at' => null,
        ];
    }
}
