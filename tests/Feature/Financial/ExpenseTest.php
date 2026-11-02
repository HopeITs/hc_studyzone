<?php

use App\Enums\ExpenseType;
use App\Enums\Roles;
use App\Enums\StockMovementType;
use App\Filament\Resources\ExpenseResource\Widgets\ExpenseTotal;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\LockedMonth;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ExpenseService;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function expenseData(int $centerId, int $userId, array $overrides = []): array
{
    return array_merge([
        'center_id' => $centerId,
        'user_id' => $userId,
        'type' => ExpenseType::Operations->value,
        'payee' => 'مورد تجريبي',
        'amount' => 500,
        'date' => today()->toDateString(),
    ], $overrides);
}

it('creates purchase stock movements for a warehouse purchase expense', function () {
    $center = testCenter();
    $user = User::factory()->create();

    $itemA = InventoryItem::factory()->create(['center_id' => $center->id]);
    $itemB = InventoryItem::factory()->create(['center_id' => $center->id]);

    $expense = app(ExpenseService::class)->create(
        expenseData($center->id, $user->id, [
            'type' => ExpenseType::WarehousePurchase->value,
            'amount' => 1300,
        ]),
        [
            ['inventory_item_id' => $itemA->id, 'quantity' => 10, 'cost' => 1000],
            ['inventory_item_id' => $itemB->id, 'quantity' => 3, 'cost' => 300],
        ],
    );

    $movements = $expense->stockMovements()->get();

    expect($movements)->toHaveCount(2)
        ->and($movements->every(fn (StockMovement $m) => $m->type === StockMovementType::Purchase))->toBeTrue()
        ->and($itemA->fresh()->current_stock)->toBe(10)
        ->and($itemB->fresh()->current_stock)->toBe(3)
        ->and($itemA->fresh()->purchase_cost)->toBe(100);

    // Deleting the expense removes its movements, so stock follows the money.
    $expense->delete();

    expect(StockMovement::where('expense_id', $expense->id)->count())->toBe(0)
        ->and($itemA->fresh()->current_stock)->toBe(0);
});

it('rejects a warehouse item from another center and persists nothing', function () {
    $center = testCenter();
    $otherCenter = testCenter();
    $user = User::factory()->create();

    $foreignItem = InventoryItem::factory()->create(['center_id' => $otherCenter->id]);

    expect(fn () => app(ExpenseService::class)->create(
        expenseData($center->id, $user->id, ['type' => ExpenseType::WarehousePurchase->value]),
        [['inventory_item_id' => $foreignItem->id, 'quantity' => 5, 'cost' => 500]],
    ))->toThrow(ValidationException::class);

    expect(Expense::count())->toBe(0)
        ->and(StockMovement::count())->toBe(0);
});

it('blocks creating an expense in a locked month', function () {
    $center = testCenter();
    $user = User::factory()->create();

    LockedMonth::create([
        'year' => now()->year,
        'month' => now()->month,
        'is_locked' => true,
        'locked_at' => now(),
    ]);

    expect(fn () => app(ExpenseService::class)->create(
        expenseData($center->id, $user->id),
    ))->toThrow(ValidationException::class);

    expect(Expense::count())->toBe(0);
});

it('blocks editing an expense once its month is locked', function () {
    $center = testCenter();
    $user = User::factory()->create();

    $expense = app(ExpenseService::class)->create(
        expenseData($center->id, $user->id),
    );

    LockedMonth::create([
        'year' => now()->year,
        'month' => now()->month,
        'is_locked' => true,
        'locked_at' => now(),
    ]);

    expect(fn () => app(ExpenseService::class)->update(
        $expense,
        ['amount' => 999],
    ))->toThrow(ValidationException::class);

    expect($expense->fresh()->amount)->toBe(500);
});

it('shows the total of the currently filtered expenses', function () {
    $center = testCenter();

    $admin = User::factory()->create(['role' => Roles::GeneralAdmin()]);
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Expense::factory()->create([
        'center_id' => $center->id,
        'user_id' => $admin->id,
        'type' => ExpenseType::Operations->value,
        'amount' => 100,
        'date' => today(),
    ]);
    Expense::factory()->create([
        'center_id' => $center->id,
        'user_id' => $admin->id,
        'type' => ExpenseType::Operations->value,
        'amount' => 250,
        'date' => today()->subDays(40),
    ]);
    Expense::factory()->create([
        'center_id' => $center->id,
        'user_id' => $admin->id,
        'type' => ExpenseType::Transport->value,
        'amount' => 999,
        'date' => today(),
    ]);

    // No filters: the widget sums everything the table shows.
    Livewire::test(ExpenseTotal::class, ['tableFilters' => []])
        ->assertSee(number_format(1349));

    // Type filter: only operations expenses are summed.
    Livewire::test(ExpenseTotal::class, [
        'tableFilters' => ['type' => ['values' => [ExpenseType::Operations->value]]],
    ])->assertSee(number_format(350));

    // Date range filter on top of the type filter.
    Livewire::test(ExpenseTotal::class, [
        'tableFilters' => [
            'type' => ['values' => [ExpenseType::Operations->value]],
            'date' => [
                'from' => today()->subDays(7)->toDateString(),
                'to' => today()->toDateString(),
            ],
        ],
    ])->assertSee(number_format(100));
});
