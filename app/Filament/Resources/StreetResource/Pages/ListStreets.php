<?php

namespace App\Filament\Resources\StreetResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\StreetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStreets extends ListRecords
{
    protected static string $resource = StreetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

        ];
    }
}
