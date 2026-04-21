<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\ServiceResource;
use App\Models\City;
use Filament\Resources\Pages\CreateRecord;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;
        $initialPrice = $this->data['initial_price'] ?? null;
        
        if ($initialPrice && is_numeric($initialPrice)) {
            $cities = City::where('code', '!=', 'na')->get();
            
            foreach ($cities as $city) {
                $record->cityPrices()->create([
                    'city_id' => $city->id,
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