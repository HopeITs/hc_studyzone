<?php

use App\Enums\ExpenseType;
use App\Enums\InvoiceStatus;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\LockedMonth;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ExpenseService;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

function lockMonth(CarbonInterface $date): LockedMonth
{
    return LockedMonth::create([
        'year' => $date->year,
        'month' => $date->month,
        'is_locked' => true,
        'locked_at' => now(),
    ]);
}

it('blocks creating an invoice in a locked current month', function () {
    $center = testCenter();

    lockMonth(now());

    expect(fn () => Invoice::factory()->create(['center_id' => $center->id]))
        ->toThrow(ValidationException::class);

    expect(Invoice::count())->toBe(0);
});

it('blocks collecting an invoice created in a locked month', function () {
    $center = testCenter();
    openShift($center);

    $invoice = Invoice::factory()->create([
        'center_id' => $center->id,
        'created_at' => now()->subMonth(),
        'total' => 100,
    ]);

    lockMonth(now()->subMonth());

    expect(fn () => $invoice->markPaid())->toThrow(ValidationException::class)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});

it('blocks cancelling an invoice when the current month is locked', function () {
    $center = testCenter();

    // Invoice lives in an open past month; only "now" is locked.
    $invoice = Invoice::factory()->create([
        'center_id' => $center->id,
        'created_at' => now()->subMonth(),
    ]);

    lockMonth(now());

    expect(fn () => $invoice->markCancelled())->toThrow(ValidationException::class)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});

it('blocks inventory purchases and count adjustments in a locked month', function () {
    $center = testCenter();
    $user = User::factory()->create();

    $item = InventoryItem::factory()->create(['center_id' => $center->id]);
    StockMovement::factory()->create([
        'center_id' => $center->id,
        'inventory_item_id' => $item->id,
        'quantity' => 10,
    ]);

    lockMonth(now());

    expect(fn () => $item->recordPurchase(5, 500, null, null, $user->id))
        ->toThrow(ValidationException::class);

    expect(fn () => $item->recordCountAdjustment(3, 'جرد', $user->id))
        ->toThrow(ValidationException::class);

    expect($item->fresh()->current_stock)->toBe(10);
});

it('blocks opening a cash shift in a locked month', function () {
    $center = testCenter();
    $user = User::factory()->create();

    lockMonth(now());

    expect(fn () => CashShift::open($center->id, $user->id, 100))
        ->toThrow(ValidationException::class);

    expect(CashShift::count())->toBe(0);
});

it('blocks closing a shift opened in a now-locked month', function () {
    $center = testCenter();

    $shift = CashShift::factory()->create([
        'center_id' => $center->id,
        'opened_at' => now()->subMonth(),
    ]);

    lockMonth(now()->subMonth());

    expect(fn () => $shift->close(0))->toThrow(ValidationException::class)
        ->and($shift->fresh()->isOpen())->toBeTrue();
});

it('blocks deleting an expense whose month is locked (model level, covers bulk delete)', function () {
    $center = testCenter();
    $user = User::factory()->create();

    $expense = Expense::factory()->create([
        'center_id' => $center->id,
        'user_id' => $user->id,
        'date' => now()->subMonth(),
    ]);

    lockMonth(now()->subMonth());

    expect(fn () => $expense->delete())->toThrow(ValidationException::class)
        ->and(Expense::whereKey($expense->id)->exists())->toBeTrue();
});

it('deleting an expense removes its stock movements through model events', function () {
    $center = testCenter();
    openShift($center);
    $user = User::factory()->create();

    $item = InventoryItem::factory()->create(['center_id' => $center->id]);

    $expense = app(ExpenseService::class)->create(
        [
            'center_id' => $center->id,
            'user_id' => $user->id,
            'type' => ExpenseType::WarehousePurchase->value,
            'payee' => 'مورد تجريبي',
            'amount' => 1000,
            'date' => today()->toDateString(),
        ],
        [['inventory_item_id' => $item->id, 'quantity' => 10, 'cost' => 1000]],
    );

    $movementIds = $expense->stockMovements()->pluck('id');
    expect($movementIds)->toHaveCount(1);

    $expense->delete();

    expect(StockMovement::whereIn('id', $movementIds)->count())->toBe(0)
        ->and($item->fresh()->current_stock)->toBe(0)
        // Model events fired, so each deleted movement left an activity row.
        ->and(Activity::query()
            ->where('subject_type', StockMovement::class)
            ->whereIn('subject_id', $movementIds)
            ->where('description', 'deleted')
            ->count())->toBe(1);
});
