<?php

namespace App\Filament\Widgets;

use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class FrequentStudentsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Frequent Students (More than X Visits)';
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 3;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $startDate = !is_null($this->filters['startDate'] ?? null)
            ? Carbon::parse($this->filters['startDate'])->startOfMonth()
            : now()->startOfYear();

        $endDate = !is_null($this->filters['endDate'] ?? null)
            ? Carbon::parse($this->filters['endDate'])->endOfMonth()
            : now()->endOfMonth();

        $cityIds = $this->filters['city'] ?? null;
        $centerIds = $this->filters['center'] ?? null;
        $minVisits = intval($this->filter ?? 1);

        // Step 1: Count entries per student per month
        $monthlyStudentEntries = StudentEntry::query()
            ->selectRaw("DATE_FORMAT(entry_time, '%Y-%m') as month, student_id, COUNT(*) as visits")
            ->whereBetween('entry_time', [$startDate, $endDate])
            ->when($centerIds, fn($q) => $q->whereIn('center_id', (array) $centerIds))
            ->when($cityIds, fn($q) => $q->whereHas('center', fn($c) => $c->whereIn('city_id', (array) $cityIds)))
            ->groupBy('month', 'student_id')
            ->havingRaw('visits >= ?', [$minVisits])
            ->get();

        // Step 2: Count unique students per month from filtered records
        $monthlyCounts = $monthlyStudentEntries
            ->groupBy('month')
            ->map(fn($group) => $group->sum('visits'))
            ->toArray();

        // Step 3: Generate month labels and data
        $months = [];
        $labels = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $key = $current->format('Y-m');
            $months[] = $key;
            $labels[] = $current->format('M Y');
            $current->addMonth();
        }

        $data = array_map(fn($month) => $monthlyCounts[$month] ?? 0, $months);

        return [
            'datasets' => [
                [
                    'label' => "Students ≥ {$minVisits} Visits",
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


    protected function getFilters(): ?array
    {
        return [
            '1' => '1',
            '5' => '5',
            '10' => '10',
            '15' => '15',
            '20' => '20',
            '25' => '25',
            '30' => '30',
        ];
    }
}
