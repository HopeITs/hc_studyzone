<?php

namespace App\Filament\Widgets;

use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class AllEntriesChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Entries per month';
    protected static ?int $sort = 1;

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

        $cityId = $this->filters['city'] ?? null;
        $centerId = $this->filters['center'] ?? null;

        $query = StudentEntry::query()
            ->selectRaw('YEAR(entry_time) as year, MONTH(entry_time) as month, COUNT(*) as total')
            ->whereBetween('entry_time', [$startDate, $endDate]);

        if ($centerId) {
            $query->where('center_id', $centerId);
        } elseif ($cityId) {
            $query->whereHas('center', function ($q) use ($cityId) {
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

        $data = array_map(fn($month) => $dataByMonth[$month] ?? 0, $months);

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Entries',
                    'data' => $data,
                    'fill' => 'start',
                ],
            ],
        ];
    }
}
