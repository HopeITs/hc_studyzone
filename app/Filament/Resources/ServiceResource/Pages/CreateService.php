<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\ServiceResource;
use App\Models\ServicePrice;
use Filament\Resources\Pages\CreateRecord;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;
        
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