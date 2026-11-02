<?php

use App\Enums\Roles;
use App\Enums\StockMovementType;
use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;

it('purchase increases stock and updates purchase metadata', function () {
    $center = testCenter();
    $user = User::factory()->create();

    $item = InventoryItem::factory()->create([
        'center_id' => $center->id,
        'user_id' => $user->id,
        'supplier' => 'مورد قديم',
        'last_purchase_date' => null,
    ]);

    $base = ['center_id' => $center->id, 'user_id' => $user->id, 'inventory_item_id' => $item->id];

    StockMovement::factory()->create($base + ['quantity' => 4]);
    StockMovement::factory()->sale(1)->create($base);

    $movement = $item->recordPurchase(10, 500, 'مورد جديد', null, $user->id);

    expect($movement->type)->toBe(StockMovementType::Purchase)
        ->and($movement->quantity)->toBe(10)
        ->and($movement->cost)->toBe(500);

    $item->refresh();

    expect($item->current_stock)->toBe(13)
        ->and($item->last_purchase_date->isToday())->toBeTrue()
        ->and($item->purchase_cost)->toBe(50)
        ->and($item->supplier)->toBe('مورد جديد');
});

it('count adjustment records the signed difference', function () {
    $center = testCenter();
    $user = User::factory()->create();

    $item = InventoryItem::factory()->create([
        'center_id' => $center->id,
        'user_id' => $user->id,
    ]);

    StockMovement::factory()->create([
        'center_id' => $center->id,
        'user_id' => $user->id,
        'inventory_item_id' => $item->id,
        'quantity' => 10,
    ]);

    // Counted less than recorded: negative adjustment.
    $movement = $item->recordCountAdjustment(7, 'جرد', $user->id);

    expect($movement->type)->toBe(StockMovementType::Adjustment)
        ->and($movement->quantity)->toBe(-3)
        ->and($movement->note)->toBe('جرد')
        ->and($item->refresh()->current_stock)->toBe(7);

    // Counted more than recorded: positive adjustment.
    $movement = $item->recordCountAdjustment(12, 'جرد ثاني', $user->id);

    expect($movement->quantity)->toBe(5)
        ->and($item->refresh()->current_stock)->toBe(12);
});

it('count adjustment with no difference creates no movement', function () {
    $center = testCenter();
    $user = User::factory()->create();

    $item = InventoryItem::factory()->create([
        'center_id' => $center->id,
        'user_id' => $user->id,
    ]);

    StockMovement::factory()->create([
        'center_id' => $center->id,
        'user_id' => $user->id,
        'inventory_item_id' => $item->id,
        'quantity' => 5,
    ]);

    $movement = $item->recordCountAdjustment(5, 'جرد مطابق', $user->id);

    expect($movement)->toBeNull()
        ->and($item->movements()->count())->toBe(1);
});

it('center admin only sees own center items', function () {
    $centerA = testCenter();
    $centerB = testCenter();

    $itemA = InventoryItem::factory()->create(['center_id' => $centerA->id]);
    $itemB = InventoryItem::factory()->create(['center_id' => $centerB->id]);

    $centerAdmin = User::factory()->create([
        'role' => Roles::CenterAdmin(),
        'center_id' => $centerA->id,
    ]);

    $this->actingAs($centerAdmin);

    $ids = InventoryItemResource::getEloquentQuery()->pluck('id');

    expect($ids)->toContain($itemA->id)
        ->not->toContain($itemB->id);

    $generalAdmin = User::factory()->create(['role' => Roles::GeneralAdmin()]);

    $this->actingAs($generalAdmin);

    $ids = InventoryItemResource::getEloquentQuery()->pluck('id');

    expect($ids)->toContain($itemA->id)
        ->toContain($itemB->id);
});
