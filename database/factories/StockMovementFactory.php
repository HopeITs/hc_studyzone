<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Center;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'center_id' => Center::factory(),
            'user_id' => User::factory(),
            'inventory_item_id' => InventoryItem::factory(),
            'type' => StockMovementType::Purchase,
            'quantity' => $this->faker->numberBetween(1, 50),
            'cost' => $this->faker->numberBetween(10, 500),
            'invoice_id' => null,
            'note' => null,
        ];
    }

    public function sale(int $quantity = 1): static
    {
        return $this->state(fn () => [
            'type' => StockMovementType::Sale,
            'quantity' => -abs($quantity),
            'cost' => null,
        ]);
    }

    public function teamAllocation(int $quantity = 1): static
    {
        return $this->state(fn () => [
            'type' => StockMovementType::TeamAllocation,
            'quantity' => -abs($quantity),
            'cost' => null,
        ]);
    }
}
