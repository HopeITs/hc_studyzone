<?php

namespace App\Helpers;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Support\Facades\Auth;

class ServiceHelper
{
    /**
     * Get all services with their minimum prices for the current user's city
     */
    public static function getServicesWithPrices(): array
    {
        $user = Auth::user();
        $cityId = $user->city_id;

        if (!$cityId) {
            return [];
        }

        $services = Service::where('is_active', true)->get();
        $result = [];

        foreach ($services as $service) {
            // Get the minimum active price for the city's centers
            $price = $service->centerPrices()
                ->where('is_active', true)
                ->whereHas('center', fn($q) => $q->where('city_id', $cityId))
                ->min('price');

            if ($price !== null) {
                $result[] = [
                    'id' => $service->id,
                    'name' => $service->name,
                    'price' => $price,
                    'category_id' => $service->category_id,
                ];
            }
        }

        return $result;
    }

    /**
     * Get default entry service (service with 'entry' unit)
     */
    public static function getDefaultEntryService(): ?array
    {
        $user = Auth::user();
        $cityId = $user->city_id;

        if (!$cityId) {
            return null;
        }

        $entryCategory = ServiceCategory::where('unit', 'entry')->first();

        if (!$entryCategory) {
            return null;
        }

        $service = Service::where('category_id', $entryCategory->id)
            ->where('is_active', true)
            ->first();

        if (!$service) {
            return null;
        }

        $price = $service->centerPrices()
            ->where('is_active', true)
            ->whereHas('center', fn($q) => $q->where('city_id', $cityId))
            ->min('price');

        if (!$price) {
            return null;
        }

        return [
            'id' => $service->id,
            'name' => $service->name,
            'price' => $price,
        ];
    }

    /**
     * Get all services for a specific city
     */
    public static function getServicesForCity(int $cityId): array
    {
        $services = Service::where('is_active', true)->get();
        $result = [];

        foreach ($services as $service) {
            $price = $service->centerPrices()
                ->where('is_active', true)
                ->whereHas('center', fn($q) => $q->where('city_id', $cityId))
                ->min('price');

            $result[] = [
                'id' => $service->id,
                'name' => $service->name,
                'price' => $price,
                'has_price' => $price !== null,
            ];
        }

        return $result;
    }
}
