<?php

namespace App\Filament\Widgets;

use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Illuminate\Support\Facades\DB;

class MultiToolUsage extends BaseWidget
{
    use InteractsWithPageFilters;

    protected function getCards(): array
    {
        $startDate = isset($this->filters['startDate'])
            ? Carbon::parse($this->filters['startDate'])
            : Carbon::now()->startOfYear();

        $endDate = isset($this->filters['endDate'])
            ? Carbon::parse($this->filters['endDate'])
            : Carbon::now();

        $centerIds = $this->filters['center'] ?? null;
        $cityIds = $this->filters['city'] ?? null;

        // Base query with applied filters
        $baseQuery = StudentEntry::query()
            ->whereBetween('entry_time', [$startDate, $endDate])
            ->when($centerIds, fn($q) => $q->whereIn('center_id', (array)$centerIds))
            ->when($cityIds, fn($q) => $q->whereHas('center', fn($q) => $q->whereIn('city_id', (array)$cityIds)));

        $totalEntries = (clone $baseQuery)->count();

        $multiToolUsers = (clone $baseQuery)
            ->whereRaw('(use_printer + use_drawer + use_mecrator) >= 2')
            ->count();

        $multiToolPercentage = $totalEntries > 0
            ? round(($multiToolUsers / $totalEntries) * 100, 2)
            : 0;

        $studentsWithMultipleCenters = StudentEntry::query()
            ->whereBetween('entry_time', [$startDate, $endDate])
            ->when($cityIds, fn($q) => $q->whereHas('center', fn($q) => $q->whereIn('city_id', (array)$cityIds)))
            ->select('student_id', DB::raw('COUNT(DISTINCT center_id) as center_count'))
            ->groupBy('student_id')
            ->having('center_count', '>', 1)
            ->get()
            ->count();

        return [
            Card::make('استخدام أكثر من خدمة', "{$multiToolPercentage}%")
                ->description('من الطلاب استخدموا أكثر من خدمة في الزيارة')
                ->color($multiToolPercentage >= 50 ? 'success' : 'warning')
                ->icon('heroicon-o-adjustments-horizontal'),

            Card::make('طلاب زاروا أكثر من مركز', $studentsWithMultipleCenters)
                ->description('عدد الطلاب الذين زاروا أكثر من مركز في المدينة المحددة')
                ->color('info')
                ->icon('heroicon-o-map-pin'),
        ];
    }
}
