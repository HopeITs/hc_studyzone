<?php

namespace App\Filament\Widgets;

use App\Models\StudentEntry;
use App\Models\Center;
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
        $startDate = $this->filters['startDate'] ?? now()->startOfYear();
        $endDate = $this->filters['endDate'] ?? now()->endOfMonth();

        $centerIds = $this->filters['center'] ?? null;
        $cityIds = $this->filters['city'] ?? null;

        $query = StudentEntry::query()
            ->select([
                'center_id',
                DB::raw('COUNT(*) as total_entries')
            ])
            ->whereBetween('entry_time', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ])
            ->whereHas('center', fn($q) => $q->where('is_active', 1))
            ->when($centerIds, fn($q) => $q->whereIn('center_id', (array)$centerIds))
            ->when($cityIds, fn($q) => $q->whereHas('center', fn($q) => $q->whereIn('city_id', (array)$cityIds))
            )
            ->groupBy('center_id')
            ->orderByDesc('total_entries')
            ->with('center:id,name') // Only load needed fields
            ->get();

        $results = $query->map(function ($entry) {
            $name = $entry->center?->getTranslation('name', app()->getLocale()) ?? 'Unnamed Center';
            return [
                'name' => $name,
                'count' => $entry->total_entries,
            ];
        });

        $labels = $results->pluck('name')->toArray();
        $data = $results->pluck('count')->toArray();



        return [
            'datasets' => [
                [
                    'label' => 'Total Entries',
                    'data' => $data,
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
