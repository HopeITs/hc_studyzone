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
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $entries = StudentEntry::query()
            ->with('student')

            ->when($this->filters['startDate'] ?? null, fn ($query) =>
                $query->whereDate('entry_time', '>=', Carbon::parse($this->filters['startDate']))
            )

            ->when($this->filters['endDate'] ?? null, fn ($query) =>
                $query->whereDate('entry_time', '<=', Carbon::parse($this->filters['endDate']))
            )

            ->when(
                !isset($this->filters['startDate']) && !isset($this->filters['endDate']),
                fn ($query) => $query->whereDate('entry_time', '>=', now()->subDays(30))
            )

            ->when($this->filters['center'] ?? null, fn ($query) =>
                $query->whereIn('center_id', (array) $this->filters['center'])
            )

            ->when($this->filters['city'] ?? null, fn ($query) =>
                $query->whereHas('center', fn ($q) =>
                    $q->whereIn('city_id', (array) $this->filters['city'])
                )
            )
            ->get();


        $grouped = $entries->groupBy(fn($entry) => optional($entry->student)->gender ?? 0)
            ->map(fn($group) => $group->count());

        $genderLabels = [
            0 => 'Unknown',
            1 => 'Male',
            2 => 'Female',
        ];

        $labels = array_values($genderLabels);
        $data = [];

        foreach ($genderLabels as $key => $label) {
            $data[] = $grouped[$key] ?? 0;
        }

        // Add total at the end
        $labels[] = 'Total';
        $data[] = array_sum($data);

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Entries',
                    'data' => $data,
                    'backgroundColor' => [
                        'rgba(255, 159, 64, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(255, 205, 86, 0.2)',
                    ],
                    'borderColor' => [
                        'rgb(255, 159, 64)',
                        'rgb(54, 162, 235)',
                        'rgb(255, 99, 132)',
                        'rgb(255, 205, 86)',
                    ],
                    'borderWidth' => 1,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
