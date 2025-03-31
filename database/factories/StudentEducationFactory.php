<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StudentEducation>
 */
class StudentEducationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'education_id' => function () {
                return \App\Models\Education::inRandomOrder()->first()->id ?? 0;
            }, // Assuming there are 10 education records
            'student_id' => function () {
                return \App\Models\Student::inRandomOrder()->first()->id;
            },
            'level' => rand(1, 7),
            'type' => rand(0, 2),
            'synced_at' =>null,
        ];
    }
}
