<?php

namespace App\Helpers;

use App\Models\Service;
use App\Models\ServicePrice;
use Carbon\Carbon;

class ServiceHelper
{
    public static function getCurrentPrice(Service $service): ?float
    {
        $activePrice = $service->prices()
            ->where('is_default', true)
            ->where('start_at', '<=', Carbon::now())
            ->where(function ($query) {
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>=', Carbon::now());
            })
            ->first();

        if ($activePrice) {
            return $activePrice->price;
        }

        $latestPrice = $service->prices()
            ->where('start_at', '<=', Carbon::now())
            ->orderBy('start_at', 'desc')
            ->first();

        return $latestPrice?->price;
    }

    public static function getServicesWithPrices(): array
    {
        $services = Service::with(['prices' => function ($query) {
            $query->where('start_at', '<=', Carbon::now())
                ->where(function ($q) {
                    $q->whereNull('end_at')
                        ->orWhere('end_at', '>=', Carbon::now());
                })
                ->orderBy('is_default', 'desc')
                ->orderBy('start_at', 'desc');
        }])->get();

        $result = [];
        foreach ($services as $service) {
            $price = self::getCurrentPrice($service);
            
            if ($price !== null) {
                $result[] = [
                    'id' => $service->id,
                    'name' => $service->name,
                    'price' => $price,
                ];
            } else {
                $result[] = [
                    'id' => $service->id,
                    'name' => $service->name,
                    'price' => 0,
                ];
            }
        }

        return $result;
    }

    public static function getDefaultEntryService(): ?array
    {
        $service = Service::where('name', 'دخولية')->first();
        
        if (!$service) {
            return null;
        }
        
        $price = self::getCurrentPrice($service);
        
        if ($price === null) {
            return null;
        }
        
        return [
            'id' => $service->id,
            'name' => $service->name,
            'price' => $price,
        ];
    }
}