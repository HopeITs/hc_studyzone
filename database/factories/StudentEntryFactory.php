<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StudentEntry>
 */
class StudentEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $center = \App\Models\Center::inRandomOrder()->first()->id;
        $user = User::where('center_id', $center)->inRandomOrder()->first()->id;
        return [
            'id' => (string) Str::uuid(),
            'student_id' => Student::inRandomOrder()->first()->id,
            'user_id' => $user,
            'center_id' => $center,
            'entry_type' => rand(0, 2),
            'use_printer' => $this->faker->boolean(),
            'use_drawer' => $this->faker->boolean(),
            'use_mecrator' => $this->faker->boolean(),
            'entry_time' => $this->faker->dateTimeBetween('-2 years', 'now')->setTime(
                $this->faker->numberBetween(9, 21), // hours between 9 and 21 (9 PM)
                $this->faker->numberBetween(0, 59), // random minute
                $this->faker->numberBetween(0, 59)  // random second
            ),
            'synced_at' => null,
        ];
    }
}
