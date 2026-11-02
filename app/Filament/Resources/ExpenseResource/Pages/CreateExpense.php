<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Enums\Roles;
use App\Filament\Resources\ExpenseResource;
use App\Models\CashShift;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

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

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
