<?php

namespace Database\Factories;

use App\Enums\CashShiftStatus;
use App\Models\CashShift;
use App\Models\Center;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashShift>
 */
class CashShiftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'center_id' => Center::factory(),
            'user_id' => User::factory(),
            'opened_at' => now()->subHours(4),
            'closed_at' => null,
            'opening_cash' => $this->faker->numberBetween(0, 1000),
            'cash_income' => null,
            'expenses_total' => null,
            'counted_cash' => null,
            'difference' => null,
            'closing_note' => null,
            'status' => CashShiftStatus::Open,
        ];
    }
}
