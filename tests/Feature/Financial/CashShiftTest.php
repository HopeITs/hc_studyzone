<?php

use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\User;

it('computes income, expenses and expected cash for a shift', function () {
    $center = testCenter();
    $user = User::factory()->create();

    $shift = CashShift::factory()->create([
        'center_id' => $center->id,
        'user_id' => $user->id,
        'opening_cash' => 100,
    ]);

    // Paid invoice linked to the shift: counted.
    Invoice::factory()->paid()->create([
        'center_id' => $center->id,
        'user_id' => $user->id,
        'shift_id' => $shift->id,
        'total' => 500,
    ]);

    // Unpaid invoice linked to the shift: ignored.
    Invoice::factory()->create([
        'center_id' => $center->id,
        'user_id' => $user->id,
        'shift_id' => $shift->id,
        'total' => 999,
    ]);

    Expense::factory()->create([
        'center_id' => $center->id,
        'user_id' => $user->id,
        'shift_id' => $shift->id,
        'amount' => 200,
    ]);

    $shift->refresh();

    expect($shift->computed_cash_income)->toBe(500)
        ->and($shift->computed_expenses)->toBe(200)
        ->and($shift->expected_cash)->toBe(400);
});
