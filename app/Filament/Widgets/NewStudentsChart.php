<?php

namespace App\Filament\Widgets;

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
        $startDate = !empty($this->filters['startDate'])
            ? Carbon::parse($this->filters['startDate'])->startOfMonth()
            : now()->startOfYear();

        $endDate = !empty($this->filters['endDate'])
            ? Carbon::parse($this->filters['endDate'])->endOfMonth()
            : now()->endOfMonth();

        $cityId = $this->filters['city'] ?? null;
        $centerId = $this->filters['center'] ?? null;

        $query = Student::query()
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as total')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_active', true);

        if ($centerId) {
            $query->whereHas('entries', function ($q) use ($centerId) {
                $q->where('center_id', $centerId);
            });
        } elseif ($cityId) {
            $query->whereHas('entries.center', function ($q) use ($cityId) {
                $q->where('city_id', $cityId);
            });
        }

        $query->groupBy('year', 'month')
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

        $data = array_map(fn ($month) => $dataByMonth[$month] ?? 0, $months);

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
