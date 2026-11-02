<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExpenseType;
use App\Enums\StockMovementType;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\LockedMonth;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    /**
     * Create an expense; a warehouse-purchase expense also records one
     * purchase stock movement per warehouse line, all in one transaction.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array{inventory_item_id?: int|string|null, quantity?: int|string|null, cost?: int|string|null}>  $warehouseItems
     *
     * @throws ValidationException on a locked month, no open shift, or an invalid warehouse line.
     */
    public function create(array $data, array $warehouseItems = []): Expense
    {
        $this->assertMonthOpen($data['date'] ?? null);

        // Every expense belongs to the center's open shift; no shift, no expense.
        $openShift = CashShift::openShiftForCenter((int) $data['center_id']);

        if ($openShift === null) {
            throw ValidationException::withMessages([
                'center_id' => __('finance.validation.no_open_shift'),
            ]);
        }

        $data['shift_id'] = $openShift->id;

        return DB::transaction(function () use ($data, $warehouseItems): Expense {
            $expense = Expense::create($data);

            $this->syncWarehouseMovements($expense, $warehouseItems);

            return $expense;
        });
    }

    /**
     * Update an expense and resync its warehouse purchase movements.
     * Both the current month of the record and the submitted date must be open.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array{inventory_item_id?: int|string|null, quantity?: int|string|null, cost?: int|string|null}>  $warehouseItems
     *
     * @throws ValidationException on a locked month or an invalid warehouse line.
     */
    public function update(Expense $expense, array $data, array $warehouseItems = []): Expense
    {
        $this->assertMonthOpen($expense->date);
        $this->assertMonthOpen($data['date'] ?? $expense->date);

        return DB::transaction(function () use ($expense, $data, $warehouseItems): Expense {
            $expense->update($data);

            $this->syncWarehouseMovements($expense->refresh(), $warehouseItems);

            return $expense;
        });
    }

    /**
     * @throws ValidationException when the month containing $date is locked.
     */
    public function assertMonthOpen(CarbonInterface|string|null $date): void
    {
        if (LockedMonth::isLocked($date)) {
            throw ValidationException::withMessages([
                'date' => __('finance.validation.month_locked'),
            ]);
        }
    }

    /**
     * Replace the purchase movements linked to this expense with the given
     * lines. A non-warehouse expense (or an empty list) ends up with none.
     *
     * @param  array<int, array{inventory_item_id?: int|string|null, quantity?: int|string|null, cost?: int|string|null}>  $warehouseItems
     *
     * @throws ValidationException on an invalid line or an item from another center.
     */
    protected function syncWarehouseMovements(Expense $expense, array $warehouseItems): void
    {
        // Model deletes (not a query-builder delete) so activity logging
        // and sync replay see each removed movement.
        $expense->stockMovements()->get()->each->delete();

        if ($expense->type !== ExpenseType::WarehousePurchase) {
            return;
        }

        $lines = $this->normalizeItems($warehouseItems);

        if ($lines === []) {
            return;
        }

        $items = InventoryItem::query()
            ->where('center_id', $expense->center_id)
            ->whereIn('id', array_column($lines, 'inventory_item_id'))
            ->get()
            ->keyBy('id');

        foreach ($lines as $line) {
            $item = $items->get($line['inventory_item_id']);

            if ($item === null) {
                throw ValidationException::withMessages([
                    'warehouse_items' => __('finance.validation.invalid_item'),
                ]);
            }

            $expense->stockMovements()->create([
                'center_id' => $expense->center_id,
                'user_id' => $expense->user_id,
                'inventory_item_id' => $item->id,
                'type' => StockMovementType::Purchase,
                'quantity' => $line['quantity'],
                'cost' => $line['cost'],
                'note' => __('finance.notes.warehouse_expense', [
                    'payee' => (string) $expense->payee,
                ]),
            ]);

            // Keep the item's purchase snapshot in line with InventoryItem::recordPurchase().
            $item->update([
                'last_purchase_date' => $expense->date,
                'purchase_cost' => (int) round($line['cost'] / max(1, $line['quantity'])),
            ]);
        }
    }

    /**
     * Drop empty repeater rows and validate the rest.
     *
     * @param  array<int, array{inventory_item_id?: int|string|null, quantity?: int|string|null, cost?: int|string|null}>  $warehouseItems
     * @return array<int, array{inventory_item_id: int, quantity: int, cost: int}>
     *
     * @throws ValidationException on a non-positive quantity or a negative cost.
     */
    protected function normalizeItems(array $warehouseItems): array
    {
        $normalized = [];

        foreach ($warehouseItems as $line) {
            $itemId = (int) ($line['inventory_item_id'] ?? 0);
            $quantity = (int) ($line['quantity'] ?? 0);
            $cost = (int) ($line['cost'] ?? 0);

            if ($itemId <= 0) {
                continue;
            }

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    'warehouse_items' => __('finance.validation.invalid_quantity'),
                ]);
            }

            if ($cost < 0) {
                throw ValidationException::withMessages([
                    'warehouse_items' => __('finance.validation.invalid_cost'),
                ]);
            }

            $normalized[] = [
                'inventory_item_id' => $itemId,
                'quantity' => $quantity,
                'cost' => $cost,
            ];
        }

        return $normalized;
    }
}
