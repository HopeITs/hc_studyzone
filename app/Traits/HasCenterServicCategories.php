<?php

namespace App\Traits;

use App\Models\ServiceCategory;
use App\Models\Service;
use App\Models\ServicePrice;
use Illuminate\Support\Facades\DB;

trait HasCenterServiceCategories
{
    /**
     * Get categories with available services count for a specific center
     */
    protected function getCategoriesForCenter(int $centerId): array
    {
        return ServiceCategory::where('is_active', 1)
            ->whereHas('services', function ($query) use ($centerId) {
                $query->where('is_active', 1)
                    ->whereHas('prices', function ($q) use ($centerId) {
                        $q->where('center_id', $centerId)
                            ->where('is_active', 1);
                    });
            })
            ->withCount([
                'services as available_services_count' => function ($query) use ($centerId) {
                    $query->where('is_active', 1)
                        ->whereHas('prices', function ($q) use ($centerId) {
                            $q->where('center_id', $centerId)
                                ->where('is_active', 1);
                        });
                }
            ])
            ->having('available_services_count', '>', 0)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function ($category) {
                return [
                    $category->id => "{$category->name} ({$category->available_services_count})"
                ];
            })
            ->toArray();
    }

    /**
     * Get services for a specific category and center
     */
    protected function getServicesForCategoryAndCenter(int $categoryId, int $centerId): array
    {
        return Service::where('category_id', $categoryId)
            ->where('is_active', 1)
            ->whereHas('prices', function ($query) use ($centerId) {
                $query->where('center_id', $centerId)
                    ->where('is_active', 1);
            })
            ->with(['prices' => function ($query) use ($centerId) {
                $query->where('center_id', $centerId)
                    ->where('is_active', 1);
            }])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function ($service) {
                $price = $service->prices->first()?->price ?? 0;
                return [
                    $service->id => "{$service->name} — " . number_format($price) . " ل.س"
                ];
            })
            ->toArray();
    }

    /**
     * Get service price for a specific center
     */
    protected function getServicePriceForCenter(int $serviceId, int $centerId): float
    {
        return (float) ServicePrice::where('service_id', $serviceId)
            ->where('center_id', $centerId)
            ->where('is_active', 1)
            ->value('price') ?? 0;
    }
}
