<?php

use App\Enums\ExpenseType;
use App\Enums\InvoiceStatus;
use App\Enums\StockMovementType;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\PosService;
use Illuminate\Validation\ValidationException;

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

it('refuses collection when no shift is open and rolls everything back', function () {
    $center = testCenter();
    $item = InventoryItem::factory()->create(['center_id' => $center->id]);
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

    expect(fn () => $invoice->markPaid())->toThrow(ValidationException::class);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid)
        ->and($item->fresh()->current_stock)->toBe(10)
        ->and(StockMovement::where('type', StockMovementType::Sale->value)->count())->toBe(0);
});

it('refuses a POS collection when no shift is open and persists no invoice', function () {
    $center = testCenter();
    $item = InventoryItem::factory()->create(['center_id' => $center->id, 'sale_price' => 100]);
    StockMovement::factory()->create([
        'center_id' => $center->id,
        'inventory_item_id' => $item->id,
        'quantity' => 10,
    ]);

    expect(fn () => app(PosService::class)->createInvoice(
        $center->id,
        [['inventory_item_id' => $item->id, 'quantity' => 2]],
        'مستفيد تجريبي',
        null,
        0,
        null,
        true,
    ))->toThrow(ValidationException::class);

    expect(Invoice::count())->toBe(0)
        ->and($item->fresh()->current_stock)->toBe(10);
});

it('refuses creating an expense when no shift is open', function () {
    $center = testCenter();
    $user = User::factory()->create();

    expect(fn () => app(ExpenseService::class)->create([
        'center_id' => $center->id,
        'user_id' => $user->id,
        'type' => ExpenseType::Operations->value,
        'payee' => 'مورد تجريبي',
        'amount' => 500,
        'date' => today()->toDateString(),
    ]))->toThrow(ValidationException::class);

    expect(Expense::count())->toBe(0);
});

it('records the open shift on paid invoices and expenses', function () {
    $center = testCenter();
    $shift = openShift($center);
    $user = User::factory()->create();

    $item = InventoryItem::factory()->create(['center_id' => $center->id, 'sale_price' => 100]);
    StockMovement::factory()->create([
        'center_id' => $center->id,
        'inventory_item_id' => $item->id,
        'quantity' => 10,
    ]);

    $invoice = app(PosService::class)->createInvoice(
        $center->id,
        [['inventory_item_id' => $item->id, 'quantity' => 2]],
        'مستفيد تجريبي',
        null,
        0,
        null,
        true,
        $user->id,
    );

    $expense = app(ExpenseService::class)->create([
        'center_id' => $center->id,
        'user_id' => $user->id,
        'type' => ExpenseType::Operations->value,
        'payee' => 'مورد تجريبي',
        'amount' => 50,
        'date' => today()->toDateString(),
    ]);

    expect($invoice->shift_id)->toBe($shift->id)
        ->and($expense->shift_id)->toBe($shift->id);
});

it('snapshots income, expenses and the difference on close', function () {
    $center = testCenter();
    $user = User::factory()->create();
    $shift = openShift($center, 100);

    Invoice::factory()->paid()->create([
        'center_id' => $center->id,
        'user_id' => $user->id,
        'shift_id' => $shift->id,
        'total' => 500,
    ]);

    Expense::factory()->create([
        'center_id' => $center->id,
        'user_id' => $user->id,
        'shift_id' => $shift->id,
        'amount' => 200,
    ]);

    // Expected: 100 + 500 - 200 = 400; counted 450 → difference 50.
    $shift->close(450, 'فائض نقدي');

    $shift->refresh();

    expect($shift->isOpen())->toBeFalse()
        ->and($shift->cash_income)->toBe(500)
        ->and($shift->expenses_total)->toBe(200)
        ->and($shift->counted_cash)->toBe(450)
        ->and($shift->difference)->toBe(50)
        ->and($shift->closing_note)->toBe('فائض نقدي')
        ->and($shift->closed_at)->not->toBeNull();
});

it('requires a note when closing with a non-zero difference', function () {
    $center = testCenter();
    $shift = openShift($center, 100);

    expect(fn () => $shift->close(150))->toThrow(ValidationException::class)
        ->and($shift->fresh()->isOpen())->toBeTrue();

    $shift->close(150, 'فرق مبرر');

    expect($shift->fresh()->isOpen())->toBeFalse()
        ->and($shift->fresh()->difference)->toBe(50);
});

it('refuses closing an already-closed shift', function () {
    $center = testCenter();
    $shift = openShift($center, 100);

    $shift->close(100);

    expect(fn () => $shift->close(100))->toThrow(ValidationException::class);
});

it('allows a single open shift per center but one per each center', function () {
    $centerA = testCenter();
    $centerB = testCenter();
    $user = User::factory()->create();

    CashShift::open($centerA->id, $user->id, 100);

    expect(fn () => CashShift::open($centerA->id, $user->id, 200))
        ->toThrow(ValidationException::class);

    expect(CashShift::where('center_id', $centerA->id)->count())->toBe(1);

    // A different center can still open its own shift.
    CashShift::open($centerB->id, $user->id, 300);

    expect(CashShift::openShiftForCenter($centerA->id))->not->toBeNull()
        ->and(CashShift::openShiftForCenter($centerB->id))->not->toBeNull();
});
