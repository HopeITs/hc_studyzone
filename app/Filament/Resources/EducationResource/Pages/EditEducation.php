<?php

namespace App\Filament\Resources\EducationResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\EducationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEducation extends EditRecord
{
    protected static string $resource = EducationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
