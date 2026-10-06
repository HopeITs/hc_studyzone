<?php

namespace App\Filament\Resources\EducationResource\Pages;

use Filament\Actions\CreateAction;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use App\Filament\Resources\EducationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEducation extends ListRecords
{
    protected static string $resource = EducationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            LocaleSwitcher::make(),
        ];
    }
}
