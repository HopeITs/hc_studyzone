<?php

use App\Models\Invoice;
use App\Models\User;

it('numbers invoices sequentially per center', function () {
    $centerA = testCenter();
    $centerB = testCenter();
    $user = User::factory()->create();

    expect(Invoice::nextNumberFor($centerA->id))->toBe(1);

    Invoice::factory()->create([
        'center_id' => $centerA->id,
        'user_id' => $user->id,
        'number' => Invoice::nextNumberFor($centerA->id),
    ]);

    expect(Invoice::nextNumberFor($centerA->id))->toBe(2);

    Invoice::factory()->create([
        'center_id' => $centerA->id,
        'user_id' => $user->id,
        'number' => Invoice::nextNumberFor($centerA->id),
    ]);

    expect(Invoice::query()->where('center_id', $centerA->id)->orderBy('number')->pluck('number')->all())
        ->toBe([1, 2]);

    // A different center starts its own sequence.
    expect(Invoice::nextNumberFor($centerB->id))->toBe(1);
});
