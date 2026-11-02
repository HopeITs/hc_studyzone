<?php

use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;

it('computes the total as line totals minus the discount', function () {
    $center = testCenter();
    $user = User::factory()->create();

    $item = InventoryItem::factory()->create(['center_id' => $center->id, 'user_id' => $user->id]);

    $invoice = Invoice::factory()->create([
        'center_id' => $center->id,
        'user_id' => $user->id,
        'discount' => 30,
    ]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'inventory_item_id' => $item->id,
        'quantity' => 2,
        'unit_price' => 100,
        'line_total' => 200,
    ]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'inventory_item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 50,
        'line_total' => 50,
    ]);

    expect($invoice->computeTotal())->toBe(220);
});

it('never computes a total below zero', function () {
    $center = testCenter();
    $user = User::factory()->create();

    $item = InventoryItem::factory()->create(['center_id' => $center->id, 'user_id' => $user->id]);

    $invoice = Invoice::factory()->create([
        'center_id' => $center->id,
        'user_id' => $user->id,
        'discount' => 500,
    ]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'inventory_item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 100,
        'line_total' => 100,
    ]);

    expect($invoice->computeTotal())->toBe(0);
});
