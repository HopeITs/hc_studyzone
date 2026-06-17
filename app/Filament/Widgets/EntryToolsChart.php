<?php

namespace App\Filament\Widgets;

use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class EntryToolsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = null;

    protected static ?int $sort = 5;

    public function getHeading(): string
    {
        return __('general.labels.entry_tool_usage_overview');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $startDate = ! is_null($this->filters['startDate'] ?? null)
            ? Carbon::parse($this->filters['startDate'])->startOfMonth()
            : now()->startOfYear();

        $endDate = ! is_null($this->filters['endDate'] ?? null)
            ? Carbon::parse($this->filters['endDate'])->endOfMonth()
            : now()->endOfMonth();

        $centerIds = $this->filters['center'] ?? null;
        $cityIds = $this->filters['city'] ?? null;

        $query = StudentEntry::query()
            ->whereBetween('entry_time', [$startDate, $endDate])
            ->when($centerIds, fn ($q) => $q->whereIn('center_id', (array) $centerIds))
            ->when($cityIds, fn ($q) => $q->whereHas('center', fn ($q) => $q->whereIn('city_id', (array) $cityIds)));

        $mecrator = (clone $query)->where('use_mecrator', true)->count();
        $printer = (clone $query)->where('use_printer', true)->count();
        $drawer = (clone $query)->where('use_drawer', true)->count();
        $plotter = (clone $query)->where('use_plotter', true)->count();
        $internet = (clone $query)->where('use_internet', true)->count();
        $reservation = (clone $query)->where('reservation', true)->count();
        $stationary = (clone $query)->where('stationary', true)->count();

        return [
            'datasets' => [
                [
                    'label' => __('general.labels.count'),
                    'data' => [$mecrator, $printer, $drawer, $plotter, $internet , $reservation , $stationary],
                    'backgroundColor' => [
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(255, 205, 86, 0.2)',
                        'rgba(153, 102, 255, 0.2)',
                    ],
                    'borderColor' => [
                        'rgb(54, 162, 235)',
                        'rgb(255, 99, 132)',
                        'rgb(255, 205, 86)',
                        'rgb(153, 102, 255)',
                    ],
                    'borderWidth' => 1,
                ],
            ],
            'labels' => [ __('general.labels.mecrator-service'),  __('general.labels.print-service'),  __('general.labels.drawer-service'),  __('general.labels.plotter-service'), __('general.labels.internet-service') ,__('general.labels.reservation'),__('general.labels.stationary') ],
        ];
    }
}
