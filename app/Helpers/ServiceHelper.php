<?php

namespace App\Helpers;

use App\Models\EntryService;
use App\Models\LockedMonth;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\StudentEntry;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ServiceHelper
{
    /**
     * Add or remove the internet service on an entry, keeping the
     * use_internet flag and the entry_services row in sync.
     * Returns the resulting use_internet state.
     */
    public static function toggleInternetService(StudentEntry $entry, bool $enable): bool
    {
        if (LockedMonth::isLocked($entry->entry_time)) {
            Notification::make()
                ->title(__('general.messages.month_locked_title'))
                ->body(__('general.messages.month_locked_body'))
                ->danger()
                ->send();

            return $entry->use_internet;
        }

        $service = Service::where('reference_column_name', 'use_internet')
            ->where('is_active', true)
            ->first();

        if (! $service) {
            Notification::make()->title('خدمة الإنترنت غير معرفة')->danger()->send();

            return $entry->use_internet;
        }

        if ($enable) {
            $price = $service->getPriceForCenter($entry->center_id);

            if ($price === null) {
                Notification::make()->title('لا يوجد سعر لخدمة الإنترنت في هذا المركز')->danger()->send();

                return $entry->use_internet;
            }

            if (! $entry->services()->where('service_id', $service->id)->exists()) {
                EntryService::create([
                    'entry_id' => $entry->id,
                    'service_id' => $service->id,
                    'amount' => 1,
                    'price_at_time' => $price,
                    'total' => $price,
                ]);
            }
        } else {
            $entry->services()->where('service_id', $service->id)->delete();
        }

        $entry->update(['use_internet' => $enable]);

        return $enable;
    }

    /**
     * Notify about selected services that have no active price in the center.
     * Returns true when every service is priced and saving may continue.
     *
     * @param  array<int|string>  $serviceIds
     * @param  array<int, int>  $prices  service_id => price
     */
    public static function ensureServicesPriced(array $serviceIds, array $prices): bool
    {
        $missing = array_diff($serviceIds, array_keys($prices));

        if (empty($missing)) {
            return true;
        }

        Notification::make()
            ->title(__('general.messages.service_price_missing_title'))
            ->body(__('general.messages.service_price_missing_body', [
                'services' => Service::whereIn('id', $missing)->pluck('name')->implode('، '),
            ]))
            ->danger()
            ->send();

        return false;
    }

    /**
     * Whether the internet service has an active price configured for the given center.
     * Memoized per request to avoid re-querying for every row in a table.
     */
    public static function centerHasInternetService(?int $centerId): bool
    {
        static $centerIds = null;

        if ($centerId === null) {
            return false;
        }

        if ($centerIds === null) {
            $service = Service::where('reference_column_name', 'use_internet')
                ->where('is_active', true)
                ->first();

            $centerIds = $service
                ? $service->centerPrices()->where('is_active', true)->pluck('center_id')->toArray()
                : [];
        }

        return in_array($centerId, $centerIds, true);
    }

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
