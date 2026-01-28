<?php

namespace App\Filament\Resources\CenterResource\Pages;

use App\Filament\Resources\CenterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCenters extends ListRecords
{
    protected static string $resource = CenterResource::class;
    use ListRecords\Concerns\Translatable;
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
//            Actions\LocaleSwitcher::make(),
        ];
    }
}
