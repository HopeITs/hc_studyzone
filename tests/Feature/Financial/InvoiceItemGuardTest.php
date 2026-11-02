<?php

use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;

it('rejects a line with both an inventory item and a service', function () {
    $center = testCenter();
    $user = User::factory()->create();

    $item = InventoryItem::factory()->create(['center_id' => $center->id, 'user_id' => $user->id]);
    $category = ServiceCategory::create(['name' => 'Test Category', 'unit' => 'session']);
    $service = Service::create(['name' => 'Test Service', 'category_id' => $category->id]);
    $invoice = Invoice::factory()->create(['center_id' => $center->id, 'user_id' => $user->id]);

    expect(fn () => InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'inventory_item_id' => $item->id,
        'service_id' => $service->id,
        'quantity' => 1,
        'unit_price' => 100,
        'line_total' => 100,
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects a line with neither an inventory item nor a service', function () {
    $center = testCenter();
    $user = User::factory()->create();

    $invoice = Invoice::factory()->create(['center_id' => $center->id, 'user_id' => $user->id]);

    expect(fn () => InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'inventory_item_id' => null,
        'service_id' => null,
        'quantity' => 1,
        'unit_price' => 100,
        'line_total' => 100,
    ]))->toThrow(InvalidArgumentException::class);
});
