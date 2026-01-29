<?php

namespace App\Filament\Widgets;

use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class ActiveCenterEntriesChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Entries Per Active Center';
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 4;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? now()->startOfMonth();
        $endDate = $this->filters['endDate'] ?? now()->endOfMonth();

        $centerIds = $this->filters['center'] ?? null;
        $cityIds = $this->filters['city'] ?? null;

        // Get the center IDs with their total entries in the period
        $entries = StudentEntry::query()
            ->whereBetween('entry_time', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ])
            ->whereHas('center', fn($q) => $q->where('is_active', 1))
            ->when($centerIds, fn($q) => $q->whereIn('center_id', (array) $centerIds))
            ->when($cityIds, fn($q) => $q->whereHas('center', fn($q2) => $q2->whereIn('city_id', (array) $cityIds)))
            ->select('center_id', DB::raw('COUNT(*) as total_entries'))
            ->groupBy('center_id')
            ->orderByDesc('total_entries')
            ->get();

        // Load centers in one go (prevents N+1)
        $centers = \App\Models\Center::whereIn('id', $entries->pluck('center_id')->toArray())->get();

        $results = $entries->map(function ($entry) use ($centers) {
            $center = $centers->where('id', $entry->center_id)->first();
            $name = $center
                ? $center->name[app()->getLocale()]
                : 'Unnamed Center';
            return [
                'name' => $name,
                'count' => $entry->total_entries,
            ];
        });

        $labels = $results->pluck('name')->toArray();
        $data = $results->pluck('count')->toArray();

        $defaultBgColors = [
            'rgba(255, 159, 64, 0.2)',
            'rgba(54, 162, 235, 0.2)',
            'rgba(255, 99, 132, 0.2)',
            'rgba(255, 205, 86, 0.2)',
            'rgba(75, 192, 192, 0.2)',
            'rgba(153, 102, 255, 0.2)',
            'rgba(201, 203, 207, 0.2)',
        ];
        $defaultBorderColors = [
            'rgb(255, 159, 64)',
            'rgb(54, 162, 235)',
            'rgb(255, 99, 132)',
            'rgb(255, 205, 86)',
            'rgb(75, 192, 192)',
            'rgb(153, 102, 255)',
            'rgb(201, 203, 207)',
        ];

        $count = count($labels);
        $bgColors = [];
        $borderColors = [];
        for ($i = 0; $i < $count; $i++) {
            $bgColors[] = $defaultBgColors[$i % count($defaultBgColors)];
            $borderColors[] = $defaultBorderColors[$i % count($defaultBorderColors)];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Entries',
                    'data' => $data,
                    'backgroundColor' => $bgColors,
                    'borderColor' => $borderColors,
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
