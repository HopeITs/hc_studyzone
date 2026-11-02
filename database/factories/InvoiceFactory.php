<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Center;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'center_id' => Center::factory(),
            'user_id' => User::factory(),
            'number' => $this->faker->unique()->numberBetween(1, 100000),
            'status' => InvoiceStatus::Unpaid,
            'beneficiary_name' => $this->faker->name(),
            'student_number' => null,
            'discount' => 0,
            'total' => 0,
            'payment_method' => PaymentMethod::Cash,
            'note' => null,
            'shift_id' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => InvoiceStatus::Paid]);
    }
}
