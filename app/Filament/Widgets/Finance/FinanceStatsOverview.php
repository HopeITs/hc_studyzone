<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Finance;

use App\Filament\Widgets\Finance\Concerns\InteractsWithFinanceScope;
use App\Services\FinanceReportService;
use App\Support\FinanceDisplay;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinanceStatsOverview extends BaseWidget
{
    use InteractsWithFinanceScope;
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $stats = app(FinanceReportService::class)->todayStats($this->resolveCenterIds());

        return [
            Stat::make(__('finance.widgets.today_sales'), FinanceDisplay::format($stats['sales']))
                ->color('success'),
            Stat::make(__('finance.widgets.today_expenses'), FinanceDisplay::format($stats['expenses']))
                ->color('danger'),
            Stat::make(__('finance.widgets.today_net'), FinanceDisplay::format($stats['net']))
                ->color($stats['net'] >= 0 ? 'success' : 'danger'),
            Stat::make(__('finance.widgets.today_invoices'), (string) $stats['invoice_count']),
            Stat::make(__('finance.widgets.low_stock_items'), (string) $stats['low_stock_count'])
                ->color($stats['low_stock_count'] > 0 ? 'warning' : 'gray'),
        ];
    }
}
