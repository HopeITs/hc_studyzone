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
        $minVisits = intval($this->filter ?? 5);

        // Get students who visited more than minVisits during the period
        $frequentStudentIds = StudentEntry::query()
            ->select('student_id')
            ->whereBetween('entry_time', [$startDate, $endDate])
            ->when($centerIds, fn($q) => $q->whereIn('center_id', (array)$centerIds))
            ->when($cityIds, fn($q) => $q->whereHas('center', fn($c) => $c->whereIn('city_id', (array)$cityIds)
            )
            )
            ->groupBy('student_id')
            ->havingRaw('COUNT(*) > ?', [$minVisits])
            ->pluck('student_id');

        // Count how many of them visited per month
        $results = StudentEntry::query()
            ->selectRaw("DATE_FORMAT(entry_time, '%Y-%m') as month, COUNT(DISTINCT student_id) as total")
            ->whereIn('student_id', $frequentStudentIds)
            ->whereBetween('entry_time', [$startDate, $endDate])
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();

        // Generate month labels
        $months = [];
        $labels = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $key = $current->format('Y-m');
            $months[] = $key;
            $labels[] = $current->format('M Y');
            $current->addMonth();
        }

        $data = array_map(fn($month) => $results[$month] ?? 0, $months);

        return [
            'datasets' => [
                [
                    'label' => "Students > {$minVisits} Visits",
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
            '10' => '10',
            '50' => '50',
            '100' => '100',
            '500' => '500',
            '1000' => '1000',
            '1500' => '1500',
            '2000'  => '2000',
            '2500'  => '2500',
            '5000'  => '5000',
            '7000'  => '7000',
            '10000' => '10000',
            '15000' => '15000',
            '25000' => '25000',
            '50000' => '50000',
            '100000' => '100000',
            '150000' => '150000',
            '250000' => '250000',
            '500000'   => '500000',

        ];
    }
}
