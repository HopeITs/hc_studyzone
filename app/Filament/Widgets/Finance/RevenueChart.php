<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Finance;

use App\Enums\Roles;
use App\Filament\Widgets\Finance\Concerns\InteractsWithFinanceScope;
use App\Models\Center;
use App\Services\FinanceReportService;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Auth;

class RevenueChart extends ChartWidget
{
    use InteractsWithFinanceScope;
    use InteractsWithPageFilters;

    public ?string $filter = '7';

    protected ?string $heading = null;

    protected static ?int $sort = 3;

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
        return __('finance.widgets.daily_revenue');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): ?array
    {
        return $this->periodFilterOptions();
    }

    protected function getData(): array
    {
        [$from, $to] = $this->resolvePeriod();
        $centerIds = $this->resolveCenterIds();

        $role = Auth::user()->role;
        $perCenter = in_array($role, [Roles::GeneralAdmin(), Roles::Observer()], true)
            && ($centerIds === [] || count($centerIds) > 1);

        $rows = app(FinanceReportService::class)->dailyRevenue($from, $to, $centerIds, $perCenter);

        $dates = [];
        $labels = [];
        $cursor = Carbon::parse($from);
        $end = Carbon::parse($to);

        while ($cursor <= $end) {
            $dates[] = $cursor->toDateString();
            $labels[] = $cursor->format('d M');
            $cursor->addDay();
        }

        if ($perCenter) {
            $centerNames = ($centerIds === []
                ? Center::query()
                : Center::whereIn('id', $centerIds)
            )->pluck('name', 'id');

            $datasets = [];
            $colorIndex = 0;

            foreach ($rows->groupBy('center_id') as $centerId => $group) {
                $byDate = $group->pluck('total', 'date');
                $color = self::COLORS[$colorIndex % count(self::COLORS)];
                $colorIndex++;

                $datasets[] = [
                    'label' => $centerNames[$centerId] ?? (string) $centerId,
                    'data' => array_map(fn (string $date): int => $this->scaleAmount((int) ($byDate[$date] ?? 0)), $dates),
                    'borderColor' => $color,
                    'backgroundColor' => $color,
                    'fill' => false,
                ];
            }
        } else {
            $byDate = $rows->pluck('total', 'date');

            $datasets = [
                [
                    'label' => __('finance.widgets.revenue'),
                    'data' => array_map(fn (string $date): int => $this->scaleAmount((int) ($byDate[$date] ?? 0)), $dates),
                    'fill' => 'start',
                ],
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }
}
