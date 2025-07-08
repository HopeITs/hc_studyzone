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
            ? Carbon::parse($this->filters['startDate'])->startOfMonth()
            : now()->startOfYear();

        $endDate = !empty($this->filters['endDate'] ?? null)
            ? Carbon::parse($this->filters['endDate'])->endOfMonth()
            : now()->endOfMonth();
        $centerIds = $this->filters['center'] ?? [];
        $cityIds = $this->filters['city'] ?? [];


        $query = Student::query()
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as total')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when(!empty($cityIds), function ($query) use ($cityIds) {
                $query->whereHas('street', function ($q) use ($cityIds) {
                    $q->whereIn('city_id', (array) $cityIds);
                });
            })
            ->when(!empty($centerIds), function ($query) use ($centerIds) {
                $cities =   Center::whereIn('id', $centerIds)->distinct()->pluck('city_id')->toArray();

                $query->whereHas('street', function ($q) use ($cities) {
                    $q->whereIn('city_id', (array) $cities);
                });
            })
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month');

        $results = $query->get();

        $dataByMonth = [];
        foreach ($results as $row) {
            $key = sprintf('%04d-%02d', $row->year, $row->month);
            $dataByMonth[$key] = $row->total;
        }

        $months = [];
        $labels = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $key = $current->format('Y-m');
            $months[] = $key;
            $labels[] = $current->format('M Y');
            $current->addMonth();
        }

        $data = array_map(fn($month) => $dataByMonth[$month] ?? 0, $months);

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
