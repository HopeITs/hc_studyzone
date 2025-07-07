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
        $startDate = !is_null($this->filters['startDate'] ?? null)
            ? Carbon::parse($this->filters['startDate'])->startOfMonth()
            : now()->startOfYear();

        $endDate = !is_null($this->filters['endDate'] ?? null)
            ? Carbon::parse($this->filters['endDate'])->endOfMonth()
            : now()->endOfMonth();

        $cityId = $this->filters['city'] ?? null;
        $centerId = $this->filters['center'] ?? null;

        $query = Student::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_active', true)
            ->groupBy('month')
            ->orderBy('month');

        // Filter by latest entry's center or city
        if ($centerId) {
            $query->whereHas('latestEntry', fn ($q) => $q->where('center_id', $centerId));
        } elseif ($cityId) {
            $query->whereHas('latestEntry.center', fn ($q) => $q->where('city_id', $cityId));
        }

        $results = $query->pluck('total', 'month')->toArray();

        // Build the full months range for the X axis
        $months = [];
        $labels = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $key = $current->format('Y-m');
            $months[] = $key;
            $labels[] = $current->format('M Y');
            $current->addMonth();
        }

        $data = array_map(fn ($month) => $results[$month] ?? 0, $months);

        return [
            'datasets' => [
                [
                    'label' => 'New Students',
                    'data' => $data,
                    'fill' => 'start',
                ],
            ],
            'labels' => $labels,
        ];
    }
}

