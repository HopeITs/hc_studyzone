<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Finance;

use App\Filament\Widgets\Finance\Concerns\InteractsWithFinanceScope;
use App\Services\FinanceReportService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class SalesByCategoryChart extends ChartWidget
{
    use InteractsWithFinanceScope;
    use InteractsWithPageFilters;

    public ?string $filter = '7';

    protected ?string $heading = null;

    protected static ?int $sort = 4;

    protected ?string $maxHeight = '300px';

    protected const COLORS = [
        'rgb(54, 162, 235)',
        'rgb(255, 99, 132)',
        'rgb(255, 159, 64)',
        'rgb(75, 192, 192)',
        'rgb(153, 102, 255)',
        'rgb(255, 205, 86)',
        'rgb(201, 203, 207)',
    ];

    public function getHeading(): string
    {
        return __('finance.widgets.sales_by_category');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getFilters(): ?array
    {
        return $this->periodFilterOptions();
    }

    protected function getData(): array
    {
        [$from, $to] = $this->resolvePeriod();

        $rows = app(FinanceReportService::class)->salesByCategory($from, $to, $this->resolveCenterIds());

        return [
            'labels' => $rows->pluck('category')->all(),
            'datasets' => [
                [
                    'label' => __('finance.widgets.sales'),
                    'data' => $rows->map(fn (object $row): int => $this->scaleAmount((int) $row->total))->all(),
                    'backgroundColor' => array_slice(self::COLORS, 0, max($rows->count(), 1)),
                ],
            ],
        ];
    }
}
