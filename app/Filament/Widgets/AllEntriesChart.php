<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Models\StudentEntry;
use Illuminate\Support\Facades\DB;
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
        $startDate = !is_null($this->filters['startDate'] ?? null)
            ? Carbon::parse($this->filters['startDate'])->startOfMonth()
            : now()->startOfYear();

        $endDate = !is_null($this->filters['endDate'] ?? null)
            ? Carbon::parse($this->filters['endDate'])->endOfMonth()
            : now()->endOfMonth();

        $cityId = $this->filters['city'] ?? null;
        $centerId = $this->filters['center'] ?? null;

        // Base query
        $query = StudentEntry::query()
            ->selectRaw("DATE_FORMAT(entry_time, '%Y-%m') as month, COUNT(*) as total")
            ->whereBetween('entry_time', [$startDate, $endDate])
            ->groupBy('month')
            ->orderBy('month');

        // Optional filters
        if ($centerId) {
            $query->where('center_id', $centerId);
        } elseif ($cityId) {
            $query->whereHas('center', fn ($q) => $q->where('city_id', $cityId));
        }

        // Results
        $results = $query->pluck('total', 'month')->toArray();

        // Generate all months in the range
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
                    'label' => 'Entries',
                    'data' => $data,
                    'fill' => 'start',

                ],
            ],
            'labels' => $labels,
        ];
    }

}
