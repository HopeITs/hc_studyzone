<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 5);
        $unitPrice = $this->faker->numberBetween(10, 300);

        return [
            'invoice_id' => Invoice::factory(),
            'inventory_item_id' => InventoryItem::factory(),
            'service_id' => null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $quantity * $unitPrice,
        ];
    }

    public function forService(?Service $service = null): static
    {
        return $this->state(fn () => [
            'inventory_item_id' => null,
            'service_id' => $service?->id ?? Service::query()->value('id'),
        ]);
    }
}
