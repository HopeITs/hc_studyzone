<?php

use App\Enums\InvoiceStatus;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\StockMovement;
use Illuminate\Validation\ValidationException;

it('deducts stock and marks the invoice paid on collection', function () {
    $center = testCenter();
    $item = InventoryItem::factory()->create(['center_id' => $center->id, 'sale_price' => 100]);
    StockMovement::factory()->create([
        'center_id' => $center->id,
        'inventory_item_id' => $item->id,
        'quantity' => 10,
    ]);

    $invoice = Invoice::factory()->create(['center_id' => $center->id, 'total' => 300]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'inventory_item_id' => $item->id,
        'quantity' => 3,
        'unit_price' => 100,
        'line_total' => 300,
    ]);

    $invoice->markPaid();

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($item->fresh()->current_stock)->toBe(7)
        ->and($invoice->stockMovements()->where('type', StockMovementType::Sale->value)->count())->toBe(1);
});

it('refuses collection when stock is insufficient', function () {
    $center = testCenter();
    $item = InventoryItem::factory()->create(['center_id' => $center->id]);
    StockMovement::factory()->create([
        'center_id' => $center->id,
        'inventory_item_id' => $item->id,
        'quantity' => 2,
    ]);

    $invoice = Invoice::factory()->create(['center_id' => $center->id]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'inventory_item_id' => $item->id,
        'quantity' => 5,
        'unit_price' => 100,
        'line_total' => 500,
    ]);

    expect(fn () => $invoice->markPaid())->toThrow(ValidationException::class)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid)
        ->and($item->fresh()->current_stock)->toBe(2);
});

it('refuses collecting the same invoice twice', function () {
    $center = testCenter();
    $item = InventoryItem::factory()->create(['center_id' => $center->id]);
    StockMovement::factory()->create([
        'center_id' => $center->id,
        'inventory_item_id' => $item->id,
        'quantity' => 10,
    ]);

    $invoice = Invoice::factory()->create(['center_id' => $center->id]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'inventory_item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 100,
        'line_total' => 100,
    ]);

    $invoice->markPaid();

    expect(fn () => $invoice->markPaid())->toThrow(ValidationException::class);
});

it('restores stock when cancelling a paid invoice', function () {
    $center = testCenter();
    $item = InventoryItem::factory()->create(['center_id' => $center->id]);
    StockMovement::factory()->create([
        'center_id' => $center->id,
        'inventory_item_id' => $item->id,
        'quantity' => 10,
    ]);

    $invoice = Invoice::factory()->create(['center_id' => $center->id]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'inventory_item_id' => $item->id,
        'quantity' => 4,
        'unit_price' => 50,
        'line_total' => 200,
    ]);

    $invoice->markPaid();
    expect($item->fresh()->current_stock)->toBe(6);

    $invoice->markCancelled();

    expect($invoice->status)->toBe(InvoiceStatus::Cancelled)
        ->and($item->fresh()->current_stock)->toBe(10);
});
