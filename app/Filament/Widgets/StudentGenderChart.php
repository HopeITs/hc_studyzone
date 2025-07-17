<?php

namespace App\Filament\Widgets;

use App\Models\Student;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class StudentGenderChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Gender Distribution (New Students)';
    protected static ?int $sort = 5;
    protected static string $color = 'primary';

    protected function getType(): string
    {
        return 'bar'; // or 'bar'
    }

    protected function getData(): array
    {
        $startDate = !is_null($this->filters['startDate'] ?? null)
            ? Carbon::parse($this->filters['startDate'])->startOfDay()
            : now()->startOfYear();

        $endDate = !is_null($this->filters['endDate'] ?? null)
            ? Carbon::parse($this->filters['endDate'])->endOfDay()
            : now()->endOfMonth();

        $cityId = $this->filters['city'] ?? null;
        $centerId = $this->filters['center'] ?? null;

        $query = Student::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($this->filters['center'] ?? null, fn($q) => $q->whereHas('entries', fn($q) => $q->whereIn('center_id', (array)$this->filters['center'])))
            ->when($this->filters['city'] ?? null, fn($q) => $q->whereHas('entries.center', fn($q) => $q->whereIn('city_id', (array)$this->filters['city'])))
            ->selectRaw('gender, COUNT(*) as total')
            ->groupBy('gender')
            ->pluck('total', 'gender')
            ->toArray();


        $query[3] = collect($query)->sum();
        $genderLabels = [
            3 => 'Total',
            1 => 'Male',
            2 => 'Female',
            0 => 'Unknown',

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
                    'label' => 'New Students',
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
                ],
            ],
            'labels' => $labels,
        ];
    }
}
