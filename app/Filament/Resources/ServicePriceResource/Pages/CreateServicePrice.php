<?php

namespace App\Filament\Resources\ServicePriceResource\Pages;

use App\Filament\Resources\ServicePriceResource;
use App\Models\ServicePrice;
use Filament\Resources\Pages\CreateRecord;

class CreateServicePrice extends CreateRecord
{
    protected static string $resource = ServicePriceResource::class;

    protected function afterCreate(): void
    {
        if ($this->record->is_default) {
            ServicePrice::where('service_id', $this->record->service_id)
                ->where('id', '!=', $this->record->id)
                ->update(['is_default' => false]);
        }
    }
}