<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Enums\Roles;
use App\Filament\Resources\InventoryItemResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateInventoryItem extends CreateRecord
{
    protected static string $resource = InventoryItemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();

        $data['user_id'] = $user->id;

        // Center admins never pick a center: theirs is forced.
        if ($user->role !== Roles::GeneralAdmin()) {
            $data['center_id'] = $user->center_id;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
