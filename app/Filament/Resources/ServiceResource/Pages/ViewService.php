<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\Resources\ServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewService extends ViewRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}