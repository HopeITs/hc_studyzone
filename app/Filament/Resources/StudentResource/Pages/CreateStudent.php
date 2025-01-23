<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();

        // Auto-fill city_id for city admins
        if ($user->role === 'city_admin') {
            $data['city_id'] = $user->city_id;
        }

        return $data;
    }
}
