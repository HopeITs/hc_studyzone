<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Enums\Roles;
use App\Filament\Resources\ExpenseResource;
use App\Models\CashShift;
use App\Services\ExpenseService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    /** @var array<int, array<string, mixed>> */
    protected array $warehouseItems = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();

        $data['user_id'] = $user->id;

        // CenterAdmin is always forced to their own center, whatever was submitted.
        if ($user->role !== Roles::GeneralAdmin()) {
            $data['center_id'] = $user->center_id;
        }

        // Attach the expense to the center's open shift, if one exists.
        $data['shift_id'] = CashShift::openShiftForCenter((int) $data['center_id'])?->id;

        // Repeater lines are not an expenses column: they become stock movements.
        $this->warehouseItems = $data['warehouse_items'] ?? [];
        unset($data['warehouse_items']);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(ExpenseService::class)->create($data, $this->warehouseItems);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
