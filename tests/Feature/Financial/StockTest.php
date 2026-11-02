<?php

use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;

it('computes current stock from signed movements', function () {
    $center = testCenter();
    $user = User::factory()->create();

    $item = InventoryItem::factory()->create([
        'center_id' => $center->id,
        'user_id' => $user->id,
        'min_stock_threshold' => 4,
    ]);

    $base = ['center_id' => $center->id, 'user_id' => $user->id, 'inventory_item_id' => $item->id];

    StockMovement::factory()->create($base + ['quantity' => 10]);
    StockMovement::factory()->sale(3)->create($base);
    StockMovement::factory()->teamAllocation(2)->create($base);

    expect($item->refresh()->current_stock)->toBe(5);
});

it('reports when stock falls to or below the threshold', function () {
    $center = testCenter();
    $user = User::factory()->create();

    $item = InventoryItem::factory()->create([
        'center_id' => $center->id,
        'user_id' => $user->id,
        'min_stock_threshold' => 5,
    ]);

    $base = ['center_id' => $center->id, 'user_id' => $user->id, 'inventory_item_id' => $item->id];

    StockMovement::factory()->create($base + ['quantity' => 5]);
    expect($item->refresh()->isBelowThreshold())->toBeTrue();

    StockMovement::factory()->create($base + ['quantity' => 1]);
    expect($item->refresh()->isBelowThreshold())->toBeFalse();
});
