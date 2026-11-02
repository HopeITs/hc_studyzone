<?php

use App\Enums\InvoiceStatus;
use App\Enums\StockMovementType;
use App\Models\Center;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\LockedMonth;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\PosService;
use Illuminate\Validation\ValidationException;

function posStockedItem(Center $center, int $stock, int $salePrice = 100): InventoryItem
{
    $item = InventoryItem::factory()->create([
        'center_id' => $center->id,
        'sale_price' => $salePrice,
    ]);

    StockMovement::factory()->create([
        'center_id' => $center->id,
        'inventory_item_id' => $item->id,
        'quantity' => $stock,
    ]);

    return $item;
}

it('collection creates a paid invoice and deducts stock with a price snapshot', function () {
    $center = testCenter();
    $user = User::factory()->create();
    $item = posStockedItem($center, 10, 100);

    $invoice = app(PosService::class)->createInvoice(
        $center->id,
        [['inventory_item_id' => $item->id, 'quantity' => 3]],
        'مستفيد تجريبي',
        null,
        0,
        null,
        true,
        $user->id,
    );

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->total)->toBe(300)
        ->and($item->fresh()->current_stock)->toBe(7)
        ->and($invoice->stockMovements()->where('type', StockMovementType::Sale->value)->count())->toBe(1);

    // Price snapshot: a later catalog price change must not affect the line.
    $item->update(['sale_price' => 999]);

    $line = $invoice->items()->first();

    expect($line->unit_price)->toBe(100)
        ->and($line->line_total)->toBe(300);
});

it('saving an unpaid invoice does not touch stock', function () {
    $center = testCenter();
    $item = posStockedItem($center, 10);

    $invoice = app(PosService::class)->createInvoice(
        $center->id,
        [['inventory_item_id' => $item->id, 'quantity' => 3]],
        'مستفيد تجريبي',
        null,
        0,
        null,
        false,
    );

    expect($invoice->status)->toBe(InvoiceStatus::Unpaid)
        ->and($item->fresh()->current_stock)->toBe(10)
        ->and($invoice->stockMovements()->count())->toBe(0);
});

it('collecting an unpaid invoice later deducts stock at that moment', function () {
    $center = testCenter();
    $item = posStockedItem($center, 10);

    $invoice = app(PosService::class)->createInvoice(
        $center->id,
        [['inventory_item_id' => $item->id, 'quantity' => 4]],
        'مستفيد تجريبي',
        null,
        0,
        null,
        false,
    );

    expect($item->fresh()->current_stock)->toBe(10);

    $invoice->markPaid();

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($item->fresh()->current_stock)->toBe(6);
});

it('rejects collection on insufficient stock and persists nothing', function () {
    $center = testCenter();
    $item = posStockedItem($center, 2);

    $invoices = Invoice::count();

    expect(fn () => app(PosService::class)->createInvoice(
        $center->id,
        [['inventory_item_id' => $item->id, 'quantity' => 5]],
        'مستفيد تجريبي',
        null,
        0,
        null,
        true,
    ))->toThrow(ValidationException::class);

    expect(Invoice::count())->toBe($invoices)
        ->and($item->fresh()->current_stock)->toBe(2);
});

it('applies the discount correctly and clamps it to the subtotal', function () {
    $center = testCenter();
    $service = app(PosService::class);

    // Percent resolution: 10% of 1000 = 100.
    expect($service->resolveDiscount(1000, 'percent', 10))->toBe(100)
        ->and($service->resolveDiscount(1000, 'percent', 150))->toBe(1000)
        ->and($service->resolveDiscount(1000, 'amount', -50))->toBe(0);

    $item = posStockedItem($center, 50, 100);

    $invoice = $service->createInvoice(
        $center->id,
        [['inventory_item_id' => $item->id, 'quantity' => 10]],
        'مستفيد تجريبي',
        null,
        100,
        null,
        true,
    );

    expect($invoice->discount)->toBe(100)
        ->and($invoice->total)->toBe(900);

    // A discount above the subtotal can never produce a negative total.
    $clamped = $service->createInvoice(
        $center->id,
        [['inventory_item_id' => $item->id, 'quantity' => 10]],
        'مستفيد تجريبي',
        null,
        5000,
        null,
        true,
    );

    expect($clamped->total)->toBe(0);
});

it('prices a service line from the center price', function () {
    $center = testCenter();

    $category = ServiceCategory::create(['name' => 'طباعة', 'unit' => 'page']);
    $service = Service::create([
        'name' => 'طباعة ورقة',
        'category_id' => $category->id,
        'is_active' => true,
    ]);
    ServicePrice::create([
        'service_id' => $service->id,
        'center_id' => $center->id,
        'price' => 250,
        'is_active' => true,
        'start_date' => now(),
    ]);

    $invoice = app(PosService::class)->createInvoice(
        $center->id,
        [['service_id' => $service->id, 'quantity' => 2]],
        'مستفيد تجريبي',
        null,
        0,
        null,
        true,
    );

    $line = $invoice->items()->first();

    expect($line->unit_price)->toBe(250)
        ->and($line->line_total)->toBe(500)
        ->and($invoice->total)->toBe(500)
        // A service line never deducts stock.
        ->and($invoice->stockMovements()->count())->toBe(0);
});

it('team allocation deducts stock without an invoice and rejects insufficient stock', function () {
    $center = testCenter();
    $user = User::factory()->create();
    $item = posStockedItem($center, 10);

    app(PosService::class)->recordTeamAllocation(
        $center->id,
        [['inventory_item_id' => $item->id, 'quantity' => 4]],
        'فريق الصيانة',
        'مستهلكات شهرية',
        $user->id,
    );

    $movement = $item->movements()
        ->where('type', StockMovementType::TeamAllocation->value)
        ->first();

    expect($item->fresh()->current_stock)->toBe(6)
        ->and($movement)->not->toBeNull()
        ->and($movement->invoice_id)->toBeNull()
        ->and($movement->quantity)->toBe(-4);

    expect(fn () => app(PosService::class)->recordTeamAllocation(
        $center->id,
        [['inventory_item_id' => $item->id, 'quantity' => 99]],
        'فريق الصيانة',
        null,
    ))->toThrow(ValidationException::class);

    expect($item->fresh()->current_stock)->toBe(6);
});

it('blocks sales in a locked month', function () {
    $center = testCenter();

    LockedMonth::create([
        'year' => now()->year,
        'month' => now()->month,
        'is_locked' => true,
        'locked_at' => now(),
    ]);

    $item = posStockedItem($center, 10);

    expect(fn () => app(PosService::class)->createInvoice(
        $center->id,
        [['inventory_item_id' => $item->id, 'quantity' => 1]],
        'مستفيد تجريبي',
        null,
        0,
        null,
        true,
    ))->toThrow(ValidationException::class);

    expect(Invoice::count())->toBe(0)
        ->and($item->fresh()->current_stock)->toBe(10);
});
