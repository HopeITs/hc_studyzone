<?php

namespace Database\Factories;

use App\Enums\InventoryUnit;
use App\Models\Center;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    public function definition(): array
    {
        $cost = $this->faker->numberBetween(5, 200);

        return [
            'center_id' => Center::factory(),
            'user_id' => User::factory(),
            'name' => $this->faker->words(2, true),
            'category' => $this->faker->randomElement(['مشروبات', 'سناكس', 'قرطاسية']),
            'unit' => $this->faker->randomElement(InventoryUnit::values()),
            'purchase_cost' => $cost,
            'sale_price' => (int) round($cost * 1.4),
            'min_stock_threshold' => $this->faker->numberBetween(5, 20),
            'supplier' => $this->faker->company(),
            'last_purchase_date' => $this->faker->dateTimeBetween('-2 months'),
            'is_active' => true,
        ];
    }
}
