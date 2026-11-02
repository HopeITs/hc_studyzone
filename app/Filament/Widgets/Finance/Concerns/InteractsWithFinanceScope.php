<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Finance\Concerns;

use App\Enums\Roles;
use App\Models\Center;
use App\Support\FinanceDisplay;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

trait InteractsWithFinanceScope
{
    public static function canView(): bool
    {
        return in_array(Auth::user()?->role, [
            Roles::GeneralAdmin(),
            Roles::CityAdmin(),
            Roles::CenterAdmin(),
            Roles::Observer(),
        ], true);
    }

    /**
     * Resolve the center ids in scope for the current user + dashboard filters.
     * An empty array means "all centers" and can only happen for GeneralAdmin / Observer.
     *
     * @return int[]
     */
    protected function resolveCenterIds(): array
    {
        $user = Auth::user();
        $role = $user->role;

        if ($role === Roles::CenterAdmin()) {
            return [(int) $user->center_id];
        }

        $filterCenters = array_values(array_map(
            'intval',
            array_filter((array) ($this->pageFilters['center'] ?? [])),
        ));

        if ($role === Roles::CityAdmin()) {
            $centerIds = Center::where('city_id', $user->city_id)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if ($filterCenters !== []) {
                $centerIds = array_values(array_intersect($centerIds, $filterCenters));
            }

            // Never fall back to "all centers" for a CityAdmin.
            return $centerIds === [] ? [0] : $centerIds;
        }

        // GeneralAdmin / Observer
        if ($filterCenters !== []) {
            return $filterCenters;
        }

        $filterCities = array_values(array_map(
            'intval',
            array_filter((array) ($this->pageFilters['city'] ?? [])),
        ));

        if ($filterCities !== []) {
            $centerIds = Center::whereIn('city_id', $filterCities)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            return $centerIds === [] ? [0] : $centerIds;
        }

        return [];
    }

    /**
     * Period for chart widgets, driven by the built-in chart `$filter` dropdown.
     *
     * @return array{0: string, 1: string} [from, to] as Y-m-d
     */
    protected function resolvePeriod(): array
    {
        return match ($this->filter) {
            '30' => [now()->subDays(29)->toDateString(), now()->toDateString()],
            'dashboard' => [
                ! empty($this->pageFilters['startDate'] ?? null)
                    ? Carbon::parse($this->pageFilters['startDate'])->toDateString()
                    : now()->startOfYear()->toDateString(),
                ! empty($this->pageFilters['endDate'] ?? null)
                    ? Carbon::parse($this->pageFilters['endDate'])->toDateString()
                    : now()->toDateString(),
            ],
            default => [now()->subDays(6)->toDateString(), now()->toDateString()],
        };
    }

    /**
     * Options for the built-in chart `$filter` dropdown.
     *
     * @return array<string, string>
     */
    protected function periodFilterOptions(): array
    {
        return [
            '7' => __('finance.widgets.last_7_days'),
            '30' => __('finance.widgets.last_30_days'),
            'dashboard' => __('finance.widgets.dashboard_filters'),
        ];
    }

    /**
     * Numeric chart value honoring the old-currency display toggle.
     */
    protected function scaleAmount(int $amount): int
    {
        return FinanceDisplay::enabled() ? $amount * 100 : $amount;
    }
}
