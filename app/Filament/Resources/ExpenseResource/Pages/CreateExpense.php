<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Enums\Roles;
use App\Filament\Resources\ExpenseResource;
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

        // ExpenseService::create attaches the center's open shift (and refuses
        // to create the expense when none is open), so no shift_id is set here.

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
