<?php

namespace Database\Factories;

use App\Models\Education;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Education>
 */
class EducationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    protected $model = Education::class;

    public function definition(): array
    {
        return [
            'name' => ['en' => $this->faker->words(2, true)], // Random name for the education
            'degree' => rand(4, 11), // Random degree from EduDegree enum
            'synced_at' => null,

        ];
    }
}
