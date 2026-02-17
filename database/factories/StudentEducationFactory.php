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
        $startDate = $this->faker->dateTimeBetween('2020-01-01', '2023-12-31')->format('Y-m-d');
        
        $hasEndDate = $this->faker->boolean(70);
        $endDate = null;
        
        if ($hasEndDate) {
            $endDate = $this->faker->dateTimeBetween($startDate, 'now')->format('Y-m-d');
        }

        return [
            'id' => (string) Str::uuid(),
            'education_id' => function () {
                return \App\Models\Education::inRandomOrder()->first()->id ?? 1;
            },
            'student_id' => function () {
                return \App\Models\Student::inRandomOrder()->first()->id;
            },
            'level' => rand(1, 7),
            'type' => rand(0, 2),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'synced_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'end_date' => null,
        ]);
    }

    public function ended(): static
    {
        return $this->state(function (array $attributes) {
            $startDate = $attributes['start_date'] ?? $this->faker->dateTimeBetween('2020-01-01', '2022-12-31')->format('Y-m-d');
            return [
                'start_date' => $startDate,
                'end_date' => $this->faker->dateTimeBetween($startDate, 'now')->format('Y-m-d'),
            ];
        });
    }

    public function withDates(string $startDate, ?string $endDate = null): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }
}