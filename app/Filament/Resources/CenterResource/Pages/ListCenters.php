<?php

namespace App\Filament\Resources\CenterResource\Pages;

use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;
use Filament\Actions\CreateAction;
use App\Filament\Resources\CenterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCenters extends ListRecords
{
    protected static string $resource = CenterResource::class;
    use Translatable;
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
//            Actions\LocaleSwitcher::make(),
        ];
    }
}
