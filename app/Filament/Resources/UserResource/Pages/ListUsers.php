<?php

namespace App\Filament\Resources\UserResource\Pages;

use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\CreateAction;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;
    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();
        $user = Auth::user();

        // City admins should only see users in their city
        if ($user->role === 'city_admin') {
            return $query->where('city_id', $user->city_id);
        }

        return $query;
    }
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
