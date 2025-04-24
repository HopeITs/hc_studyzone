<?php

namespace App\Filament\Widgets;

use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class HourlyAverageChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Percentage of Entries per Hour (Only Active Days)';
    protected static ?int $sort = 4;

    protected function getType(): string
    {
        return 'bar'; // Using a bar chart
    }

    protected function getData(): array
    {
        $startDate = !is_null($this->filters['startDate'] ?? null)
            ? Carbon::parse($this->filters['startDate'])->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $endDate = !is_null($this->filters['endDate'] ?? null)
            ? Carbon::parse($this->filters['endDate'])->endOfDay()
            : now()->endOfMonth()->endOfDay();

        $centerIds = $this->filters['center'] ?? null;
        $cityIds = $this->filters['city'] ?? null;

        // Count only the number of distinct days that had entries
        $activeDays = StudentEntry::query()
            ->whereBetween('entry_time', [$startDate, $endDate])
            ->when($centerIds, fn ($q) => $q->whereIn('center_id', (array) $centerIds))
            ->when($cityIds, fn ($q) =>
            $q->whereHas('center', fn ($q) => $q->whereIn('city_id', (array) $cityIds))
            )
            ->selectRaw('DATE(entry_time) as day')
            ->distinct()
            ->pluck('day')
            ->count();

        // Get entries grouped by hour
        $rawData = StudentEntry::query()
            ->selectRaw("HOUR(entry_time) as hour, COUNT(*) as total")
            ->whereBetween('entry_time', [$startDate, $endDate])
            ->whereBetween(DB::raw('HOUR(entry_time)'), [9, 21])
            ->when($centerIds, fn ($q) => $q->whereIn('center_id', (array) $centerIds))
            ->when($cityIds, fn ($q) =>
            $q->whereHas('center', fn ($q) => $q->whereIn('city_id', (array) $cityIds))
            )
            ->groupBy(DB::raw('HOUR(entry_time)'))
            ->orderBy('hour')
            ->pluck('total', 'hour')
            ->toArray();

        $labels = [];
        $data = [];

        for ($hour = 9; $hour <= 21; $hour++) {
            $labels[] = Carbon::createFromTime($hour)->format('g A');
            $count = $rawData[$hour] ?? 0;
            // Percentage of entries during this hour across active days
            $data[] = $activeDays > 0 ? round(($count / $activeDays), 2) : 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Avg Entries per Active Day',
                    'data' => $data,
                    'fill' => 'start',
                    'backgroundColor' => [
                        'rgba(255, 159, 64, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 99, 132, 0.2)',

                        'rgba(255, 205, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',

                        'rgba(153, 102, 255, 0.2)',
                        'rgba(201, 203, 207, 0.2)'
                    ],
                    'borderColor' => [
                        'rgb(255, 159, 64)',
                        'rgb(54, 162, 235)',
                        'rgb(255, 99, 132)',
                        'rgb(255, 205, 86)',
                        'rgb(75, 192, 192)',
                        'rgb(153, 102, 255)',
                        'rgb(201, 203, 207)'
                    ],
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
