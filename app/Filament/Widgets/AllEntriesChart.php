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
            ? Carbon::parse($this->filters['startDate'])->startOfDay()
            : now()->startOfYear();

        $endDate = !empty($this->filters['endDate'] ?? null)
            ? Carbon::parse($this->filters['endDate'])->endOfDay()
            : now()->endOfDay();

        $cityId = $this->filters['city'] ?? null;
        $centerId = $this->filters['center'] ?? null;

        $diffInDays = $startDate->diffInDays($endDate);

        $query = StudentEntry::query();

        if ($diffInDays < 31) {
            // Daily stats
            $query->selectRaw('DATE(entry_time) as day, COUNT(*) as total')
                ->whereBetween('entry_time', [$startDate, $endDate])
                ->groupBy('day')
                ->orderBy('day');
        } else {
            // Monthly stats
            $query->selectRaw('YEAR(entry_time) as year, MONTH(entry_time) as month, COUNT(*) as total')
                ->whereBetween('entry_time', [$startDate, $endDate])
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month');
        }

        if ($centerId) {
            $query->whereIn('center_id', $centerId);
        } elseif ($cityId) {
            $query->whereHas('center', function ($q) use ($cityId) {
                $q->whereIn('city_id', $cityId);
            });
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
                    'label' => 'Entries',
                    'data' => $data,
                    'fill' => 'start',
                ],
            ],
        ];
    }



}
