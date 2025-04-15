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
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $centerIds = $this->filters['center'] ?? null;
        $cityIds = $this->filters['city'] ?? null;

        $query = StudentEntry::query()
            ->when($startDate, fn($q) => $q->whereDate('entry_time', '>=', Carbon::parse($startDate)))
            ->when($endDate, fn($q) => $q->whereDate('entry_time', '<=', Carbon::parse($endDate)))
            ->when($centerIds, fn($q) => $q->whereIn('center_id', (array)$centerIds))
            ->when($cityIds, fn($q) => $q->whereHas('center', fn($q) => $q->whereIn('city_id', (array)$cityIds))
            );

        $total = (clone $query)->count();

        $multiTool = (clone $query)
            ->whereRaw('(use_printer + use_drawer + use_mecrator) >= 2')
            ->count();

        $percentage = $total > 0 ? round(($multiTool / $total) * 100, 2) : 0;

        $studentsWithMultipleCenters = StudentEntry::query()
            ->when($startDate, fn ($q) => $q->whereDate('entry_time', '>=', Carbon::parse($startDate)))
            ->when($endDate, fn ($q) => $q->whereDate('entry_time', '<=', Carbon::parse($endDate)))
            ->when($cityIds, fn ($q) =>
            $q->whereHas('center', fn ($q) => $q->whereIn('city_id', (array) $cityIds))
            )
            ->select('student_id', DB::raw('COUNT(DISTINCT center_id) as center_count'))
            ->groupBy('student_id')
            ->having('center_count', '>', 1)
            ->count();

        return [
            Card::make('استخدام أكثر من خدمة', $percentage . '%')
                ->description("من الطلاب استخدموا أكثر من خدمة في الزيارة")
                ->color($percentage >= 50 ? 'success' : 'warning')
                ->icon('heroicon-o-adjustments-horizontal'),
            Card::make('طلاب زاروا أكثر من مركز', $studentsWithMultipleCenters)
                ->description('عدد الطلاب الذين زاروا أكثر من مركز في المدينة المحددة')
                ->icon('heroicon-o-map-pin')
                ->color('info'),
        ];
    }
}
