<?php

namespace App\Filament\Widgets;

use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class ActiveCenterEntriesChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Entries Per Active Center';
    protected static ?int $sort = 5;
    // protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        // Filter on the dates:
        $startDate = !is_null($this->filters['startDate'] ?? null)
            ? Carbon::parse($this->filters['startDate'])->startOfDay()
            : now()->startOfYear()->startOfDay();

        $endDate = !is_null($this->filters['endDate'] ?? null)
            ? Carbon::parse($this->filters['endDate'])->endOfDay()
            : now()->endOfMonth()->endOfDay();

        $centerIds = $this->filters['center'] ?? null;
        $cityIds = $this->filters['city'] ?? null;

        // Fetch entries with the active centers and display them:
        $results = StudentEntry::query()
            ->whereHas('center', fn ($q) => $q->where('is_active', 1))
            ->whereBetween('entry_time', [$startDate, $endDate])
            ->when($centerIds, fn ($q) => $q->whereIn('center_id', (array) $centerIds))
            ->when($cityIds, fn ($q) =>
                $q->whereHas('center', fn ($q) => $q->whereIn('city_id', (array) $cityIds))
            )
            ->with('center')
            ->get()
            ->groupBy('center_id')
            ->map(function ($entries) {
                $center = $entries->first()->center;
                return [
                    'name' => $center->getTranslation('name', 'en') ?? 'Unnamed',
                    'count' => $entries->count(),
                ];
            })
            ->sortByDesc('count')
            ->values();

        $labels = $results->pluck('name')->toArray();
        $data = $results->pluck('count')->toArray();

        $backgroundColors = [
            'rgba(255, 99, 132, 0.2)',
            'rgba(54, 162, 235, 0.2)',
            'rgba(255, 206, 86, 0.2)',
            'rgba(75, 192, 192, 0.2)',
            'rgba(153, 102, 255, 0.2)',
            'rgba(255, 159, 64, 0.2)',
            'rgba(201, 203, 207, 0.2)',
        ];

        $borderColors = [
            'rgb(255, 99, 132)',
            'rgb(54, 162, 235)',
            'rgb(255, 206, 86)',
            'rgb(75, 192, 192)',
            'rgb(153, 102, 255)',
            'rgb(255, 159, 64)',
            'rgb(201, 203, 207)',
        ];

        $backgroundColor = [];
        $borderColor = [];

        for ($i = 0; $i < count($data); $i++) {
            $backgroundColor[] = $backgroundColors[$i % count($backgroundColors)];
            $borderColor[] = $borderColors[$i % count($borderColors)];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Entries',
                    'data' => $data,
                    'backgroundColor' => $backgroundColor,
                    'borderColor' => $borderColor,
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
