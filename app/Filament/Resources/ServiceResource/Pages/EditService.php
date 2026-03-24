<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\ServiceResource;
use App\Models\ServicePrice;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        $record = $this->record;

        // Ensure only one price is default after editing
        $activePrice = $record->prices()->where('is_default', true)->first();

        if ($activePrice) {
            ServicePrice::where('service_id', $record->id)
                ->where('id', '!=', $activePrice->id)
                ->update(['is_default' => false, 'end_at' => now()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}