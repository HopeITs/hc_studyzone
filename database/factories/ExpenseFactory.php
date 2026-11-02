<?php

namespace Database\Factories;

use App\Enums\ExpenseType;
use App\Models\Center;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'center_id' => Center::factory(),
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(ExpenseType::values()),
            'payee' => $this->faker->company(),
            'amount' => $this->faker->numberBetween(50, 2000),
            'date' => $this->faker->dateTimeBetween('-1 month'),
            'note' => null,
            'receipt_path' => null,
            'shift_id' => null,
        ];
    }
}
