<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\City;
use App\Models\Street;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        $street_id = Street::inRandomOrder()->first()->id;
        return [
            'id' => (string) Str::uuid(),
            'street_id'=> $street_id,
            'firstname' => $this->faker->firstName(),
            'lastname' => $this->faker->lastName(),
            'father_name' => $this->faker->firstNameMale(),
            'national_number' => $this->faker->unique()->numerify('##########'),
            'phone' => $this->faker->unique()->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'dob' => $this->faker->dateTimeBetween('-50 years', '-15 years'),
            'gender' =>  rand(1, 2),
            'religion' => rand(0, 2),
            'address' => $this->faker->address(),
            'notes' => $this->faker->sentence(),
            'is_student' => true,
            'is_employed' => $this->faker->boolean(),
            'is_active' => true,
            'synced_at' => null,
            'created_at' => $this->faker->dateTimeBetween('-2 year', 'now'),
        ];
    }
}
