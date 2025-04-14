<?php

namespace App\Filament\Widgets;

use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class EntryGenderChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Gender Distribution (Entries)';
    protected static ?int $sort = 6;

    protected function getType(): string
    {
        return 'bar'; // or 'bar'
    }

    protected function getData(): array
    {
        $query = StudentEntry::query()
            ->with('student')
            ->when($this->filters['startDate'] ?? null, fn($q) => $q->whereDate('entry_time', '>=', Carbon::parse($this->filters['startDate']))
            )
            ->when($this->filters['endDate'] ?? null, fn($q) => $q->whereDate('entry_time', '<=', Carbon::parse($this->filters['endDate']))
            )
            ->when($this->filters['center'] ?? null, fn($q) => $q->whereIn('center_id', (array)$this->filters['center'])
            )
            ->when($this->filters['city'] ?? null, fn($q) => $q->whereHas('center', fn($q) => $q->whereIn('city_id', (array)$this->filters['city'])
            )
            )
            ->get()
            ->groupBy(fn($entry) => $entry->student->gender ?? -1)
            ->map(fn($group) => $group->count());
        $query[0] = collect($query)->sum();
        $genderLabels = [
            0 => 'Total',
            1 => 'Male',
            2 => 'Female',
        ];

        $labels = [];
        $data = [];

        foreach ($genderLabels as $key => $label) {
            $labels[] = $label;
            $data[] = $query[$key] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Entries',
                    'data' => $data,
                    'backgroundColor' => [
                        'rgba(255, 159, 64, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 99, 132, 0.2)',

                    ],
                    'borderColor' => [
                        'rgb(255, 159, 64)',
                        'rgb(54, 162, 235)',
                        'rgb(255, 99, 132)',

                    ],
                ],
            ],
            'labels' => $labels,
        ];
    }
}
