<?php

namespace App\Filament\Widgets;

use App\Exports\EntriesByCenterExport;
use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Widgets\Widget;
use Maatwebsite\Excel\Facades\Excel;

class EntriesByCenterTable extends Widget
{
    protected static string $view = 'filament.widgets.entries-by-center-table';

    protected static ?string $heading = 'Entries by Center';
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 3;
    public function getViewData(): array
    {
        $startDate = now()->startOfYear();
        $endDate   = now()->endOfMonth();

        $results = StudentEntry::query()
            ->selectRaw('YEAR(entry_time) as year, MONTH(entry_time) as month, center_id, COUNT(*) as total')
            ->whereBetween('entry_time', [$startDate, $endDate])
            ->groupBy('year', 'month', 'center_id')
            ->with('center:id,name')
            ->get();

        $centers = $results->pluck('center.name', 'center_id')->unique();
        $data = [];

        $current = $startDate->copy();
        while ($current <= $endDate) {
            $row = ['month' => $current->format('M Y')];
            foreach ($centers as $id => $name) {
                $match = $results->first(fn($r) =>
                    $r->year == $current->year &&
                    $r->month == $current->month &&
                    $r->center_id == $id
                );
                $row[$name] = $match?->total ?? 0;
            }
            $data[] = $row;
            $current->addMonth();
        }

        return compact('data', 'centers');
    }
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export to Excel')
                ->action(function () {
                    // Get start and end dates for the export
                    $startDate = now()->startOfYear();
                    $endDate   = now()->endOfMonth();

                    // Trigger the Excel download
                    return Excel::download(
                        new EntriesByCenterExport($startDate, $endDate),
                        'entries_by_center.xlsx'
                    );
                }),
        ];
    }
}
