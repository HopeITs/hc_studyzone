<?php

namespace App\Filament\Widgets;

use App\Models\Center;
use App\Models\Student;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class NewStudentsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'New Students';
    protected static ?int $sort = 2;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $startDate = !empty($this->filters['startDate'] ?? null)
            ? Carbon::parse($this->filters['startDate'])->startOfDay()
            : now()->startOfYear();

        $endDate = !empty($this->filters['endDate'] ?? null)
            ? Carbon::parse($this->filters['endDate'])->endOfDay()
            : now()->endOfDay();

        $centerIds = $this->filters['center'] ?? [];
        $cityIds = $this->filters['city'] ?? [];

        $diffInDays = $startDate->diffInDays($endDate);

        $query = Student::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when(!empty($cityIds), function ($query) use ($cityIds) {
                $query->whereHas('street', function ($q) use ($cityIds) {
                    $q->whereIn('city_id', (array) $cityIds);
                });
            })
            ->when(!empty($centerIds), function ($query) use ($centerIds) {
                $cities = Center::whereIn('id', $centerIds)
                    ->distinct()
                    ->pluck('city_id')
                    ->toArray();

                $query->whereHas('street', function ($q) use ($cities) {
                    $q->whereIn('city_id', (array) $cities);
                });
            });

        if ($diffInDays < 31) {
            // Daily stats
            $query->selectRaw('DATE(created_at) as day, COUNT(*) as total')
                ->groupBy('day')
                ->orderBy('day');
        } else {
            // Monthly stats
            $query->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as total')
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month');
        }

        $results = $query->get();

        $labels = [];
        $data = [];

        if ($diffInDays < 31) {
            // Fill missing days
            $dataByDay = $results->pluck('total', 'day')->toArray();
            $current = $startDate->copy();
            while ($current <= $endDate) {
                $key = $current->format('Y-m-d');
                $labels[] = $current->format('d M');
                $data[] = $dataByDay[$key] ?? 0;
                $current->addDay();
            }
        } else {
            // Fill missing months
            $dataByMonth = [];
            foreach ($results as $row) {
                $key = sprintf('%04d-%02d', $row->year, $row->month);
                $dataByMonth[$key] = $row->total;
            }

            $current = $startDate->copy()->startOfMonth();
            while ($current <= $endDate) {
                $key = $current->format('Y-m');
                $labels[] = $current->format('M Y');
                $data[] = $dataByMonth[$key] ?? 0;
                $current->addMonth();
            }
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'New Students',
                    'data' => $data,
                    'fill' => 'start',
                ],
            ],
        ];
    }

}
