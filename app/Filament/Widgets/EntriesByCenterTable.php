<?php

namespace App\Filament\Widgets;

use App\Enums\Roles;
use App\Exports\EntriesByCenterExport;
use App\Models\Center;
use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Maatwebsite\Excel\Facades\Excel;

class EntriesByCenterTable extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.entries-by-center-table';

    protected static ?string $heading = null;
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 3;

    public function getHeading(): string
    {
        return __('general.labels.entries_by_center');
    }

    public function getViewData(): array
    {
        $startDate = !is_null($this->pageFilters['startDate'] ?? null)
            ? Carbon::parse($this->pageFilters['startDate'])->startOfDay()
            : now()->startOfYear()->startOfDay();

        $endDate = !is_null($this->pageFilters['endDate'] ?? null)
            ? Carbon::parse($this->pageFilters['endDate'])->endOfDay()
            : now()->endOfMonth()->endOfDay();

        $cityIds = $this->pageFilters['city'] ?? null;
        $centerIds = $this->pageFilters['center'] ?? null;

        $centers = Center::query()
            ->where('is_active', true)
            ->when($centerIds, fn($q) => $q->whereIn('id', $centerIds))
            ->when($cityIds, fn($q) => $q->whereIn('city_id', $cityIds))
            ->with([
                'entries' => function ($q) use ($startDate,  $endDate) {
                    $q->selectRaw('center_id, YEAR(entry_time) as year, MONTH(entry_time) as month, COUNT(*) as total')
                        ->whereBetween('entry_time', [$startDate, $endDate])
                        ->groupBy('center_id', 'year', 'month');
                }
            ])
            ->get(['id', 'name']);

        $data = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $row = ['month' => $current->format('M Y')];
            foreach ($centers as $center) {
                $match = $center->entries->first(fn($r) => $r->year == $current->year &&
                    $r->month == $current->month
                );
                $row[$center->name] = $match?->total ?? 0;
            }
            $data[] = $row;
            $current->addMonth();
        }

        return [
            'data' => $data,
            'centers' => $centers->pluck('name'),
            'from' => $startDate->format('Y-m-d'),
            'to' => $endDate->format('Y-m-d'),
            'canExport' => true,
        ];
    }
}
