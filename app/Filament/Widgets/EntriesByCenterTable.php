<?php

namespace App\Filament\Widgets;

use App\Enums\Roles;
use App\Exports\EntriesByCenterExport;
use App\Models\Center;
use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Tables\Actions\ExportAction;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Maatwebsite\Excel\Facades\Excel;

class EntriesByCenterTable extends Widget
{
    use InteractsWithPageFilters;

    protected static string $view = 'filament.widgets.entries-by-center-table';

    protected static ?string $heading = 'Entries by Center';
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 3;



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
                'entries' => function ($q) use ($startDate,  $endDate) {
                    $q->selectRaw('center_id, YEAR(entry_time) as year, MONTH(entry_time) as month, COUNT(*) as total')
                        ->whereBetween('entry_time', [$startDate, $endDate])
                        ->groupBy('center_id', 'year', 'month');
                }
            ])
            ->get(['id', 'name']);

        // pivot rows
        $data = [];
        $current = $startDate->copy();
        $locale = app()->getLocale();
        while ($current <= $endDate) {
            $row = ['month' => $current->format('M Y')];
            foreach ($centers as $center) {
                $match = $center->entries->first(fn($r) => $r->year == $current->year &&
                    $r->month == $current->month
                );
                $row[$center->name[$locale]] = $match?->total ?? 0;
            }
            $data[] = $row;
            $current->addMonth();
        }

        return [
            'data' => $data,
            'centers' => $centers->pluck('name'),
            'from' => $startDate->format('Y-m-d'),
            'to' => $endDate->format('Y-m-d'),
            'canExport' => auth()->user()->role !== Roles::Observer(),
        ];
    }


}
