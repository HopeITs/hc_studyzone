<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();

        // If logged-in user is a city admin, restrict city selection
        if ($user->role === 'city_admin' && isset($data['role']) && $data['role'] === 'center_admin') {
            $data['city_id'] = $user->city_id;
        }

        return $data;
    }
}
