<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\ServiceResource;
use App\Models\Center;
use Filament\Resources\Pages\CreateRecord;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;
        $initialPrice = $this->data['initial_price'] ?? null;

        if ($initialPrice && is_numeric($initialPrice)) {
            $centers = Center::where('is_active',true)->get();

            foreach ($centers as $center) {
                $record->centerPrices()->create([
                    'center_id' => $center->id,
                    'price' => $initialPrice,
                    'is_active' => true,
                ]);
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
