<?php

namespace App\Filament\Widgets;

use App\Exports\EntriesByCenterExport;
use App\Models\Center;
use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Tables\Actions\ExportAction;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Maatwebsite\Excel\Facades\Excel;

class ActiveDaysByCenter extends Widget
{
    use InteractsWithPageFilters;

    protected static string $view = 'filament.widgets.active-days-by-center';

    protected static ?string $heading = null;
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 3;

    public function getHeading(): string
    {
        return __('general.labels.active_days_by_center');
    }



    public function getViewData(): array
    {
        $startDate = !is_null($this->filters['startDate'] ?? null)
            ? Carbon::parse($this->filters['startDate'])->startOfDay()
            : now()->startOfYear()->startOfDay();

        $endDate = !is_null($this->filters['endDate'] ?? null)
            ? Carbon::parse($this->filters['endDate'])->endOfDay()
            : now()->endOfMonth()->endOfDay();

        $cityIds = $this->filters['city'] ?? null;
        $centerIds = $this->filters['center'] ?? null;
        // base query for centers
        $centers = Center::query()
            ->where('is_active', true)
            ->when($centerIds, fn($q) => $q->whereIn('id', $centerIds))
            ->when($cityIds, fn($q) => $q->whereIn('city_id', $cityIds))
            ->with([
                'entries' => function ($q) use ($startDate, $endDate) {
                    $q->selectRaw('center_id, YEAR(entry_time) as year, MONTH(entry_time) as month, COUNT(DISTINCT DATE(entry_time)) as active_days_count')
                        ->whereBetween('entry_time', [$startDate, $endDate])
                        ->groupBy('center_id', 'year', 'month');
                }
            ])
            ->get(['id', 'name']);

// Pivot rows
        $data = [];
        $current = $startDate->copy()->startOfMonth();

        while ($current <= $endDate) {
            $monthKey = $current->format('M Y');
            $row = ['month' => $monthKey];

            foreach ($centers as $center) {
                // Find the matching month record for this center
                $match = $center->entries->first(fn($r) =>
                    $r->year == $current->year &&
                    $r->month == $current->month
                );


                // Access 'active_days_count' (the alias we defined in the query)
                $row[$center->name] = $match ? (int)$match->active_days_count : 0;
            }

            $data[] = $row;
            $current->addMonth();
        }

        return [
            'data' => $data,
            'centers' => $centers->pluck('name'),
            'from' => $startDate->format('Y-m-d'),
            'to' => $endDate->format('Y-m-d'),
        ];
    }


}
