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
        $startDate = !is_null($this->filters['startDate'] ?? null)
            ? Carbon::parse($this->filters['startDate'])->startOfMonth()
            : now()->startOfYear();

        $endDate = !is_null($this->filters['endDate'] ?? null)
            ? Carbon::parse($this->filters['endDate'])->endOfMonth()
            : now()->endOfMonth();

        $cityId = $this->filters['city'] ?? null;
        $centerId = $this->filters['center'] ?? null;

        $query = StudentEntry::query()
            ->selectRaw('COALESCE(hcsz_students.gender, 0) as gender, COUNT(*) as total')
            ->join('students', 'students.id', '=', 'student_entries.student_id')
            ->whereBetween('entry_time', [$startDate, $endDate])
            ->when($centerId, fn($q) => $q->whereIn('center_id', (array)$centerId))
            ->when($cityId, fn($q) =>
            $q->whereHas('center', fn($c) => $c->whereIn('city_id', (array)$cityId))
            )
            ->groupBy('gender')
            ->pluck('total', 'gender');

        $genderLabels = [
            3 => 'Total',
            1 => 'Male',
            2 => 'Female',
            0 => 'Unknown',
        ];

        $labels = array_values($genderLabels);
        $data = [
            $query->sum(), // Total
            $query[1] ?? 0,
            $query[2] ?? 0,
            $query[0] ?? 0,
        ];

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
