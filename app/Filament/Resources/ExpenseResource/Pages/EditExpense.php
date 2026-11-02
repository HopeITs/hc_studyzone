<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Models\StockMovement;
use App\Services\ExpenseService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

    /** @var array<int, array<string, mixed>> */
    protected array $warehouseItems = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['warehouse_items'] = $this->getRecord()
            ->stockMovements()
            ->get()
            ->map(fn (StockMovement $movement): array => [
                'inventory_item_id' => $movement->inventory_item_id,
                'quantity' => $movement->quantity,
                'cost' => $movement->cost,
            ])
            ->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Repeater lines are not an expenses column: they become stock movements.
        $this->warehouseItems = $data['warehouse_items'] ?? [];
        unset($data['warehouse_items']);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(ExpenseService::class)->update($record, $data, $this->warehouseItems);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
