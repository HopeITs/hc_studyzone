<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExpenseResource\Widgets;

use App\Filament\Resources\ExpenseResource\Pages\ListExpenses;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExpenseTotal extends BaseWidget
{
    use InteractsWithPageTable;

    protected ?string $pollingInterval = null;

    protected function getTablePage(): string
    {
        return ListExpenses::class;
    }

    protected function getColumns(): int
    {
        return 1;
    }

    protected function getStats(): array
    {
        // Sum what the table currently shows (role scope + active filters).
        $totals = $this->getPageTableQuery()
            ->reorder()
            ->toBase()
            ->selectRaw('COALESCE(SUM(amount), 0) as total, COUNT(*) as records')
            ->first();

        return [
            Stat::make(
                __('finance.labels.filtered_total'),
                number_format((int) $totals->total).' SYP'
            )->description(__('finance.labels.filtered_count', [
                'count' => (int) $totals->records,
            ])),
        ];
    }
}
