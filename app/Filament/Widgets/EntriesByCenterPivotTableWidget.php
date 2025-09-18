<?php

namespace App\Filament\Widgets;

use App\Exports\EntriesByCenterExport;
use App\Models\Center;
use Carbon\Carbon;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class EntriesByCenterPivotTableWidget extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Entries by Center Over Time';
    protected static ?int $sort = 10;
    protected int | string | array $columnSpan = 'full';

    protected function getQuery(): Builder
    {
        return Center::query();
    }

    public function table(Table $table): Table
    {
        $cityIds = $this->filters['city'] ?? null;
        $centerIds = $this->filters['center'] ?? null;

        $centersQuery = Center::query();
        if (!empty($centerIds)) {
            $centersQuery->whereIn('id', $centerIds);
        } elseif (!empty($cityIds)) {
            $centersQuery->whereIn('city_id', $cityIds);
        }
        $centers = $centersQuery->get();

        $startDate = Carbon::parse($this->filters['startDate'] ?? now()->startOfYear());
        $endDate = Carbon::parse($this->filters['endDate'] ?? now());
        $diffInDays = $endDate->diffInDays($startDate);
        $groupByDay = $diffInDays <= 31;

        $columns = [
            TextColumn::make('period')->label($groupByDay ? 'Day' : 'Month')->sortable(),
        ];

        foreach ($centers as $center) {
            $columns[] = TextColumn::make('center_' . $center->id)
                ->label($center->name)
                ->numeric()
                ->sortable();
        }

        return $table
            ->query($this->getQuery())
            ->columns($columns)
            ->paginated(false)
            ->headerActions([
                Action::make('export')
                    ->label('Export to Excel')
                    ->color('success')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(fn() => Excel::download(new EntriesByCenterExport($this->filters), 'entries_by_center.xlsx')),
            ]);
    }

    protected function getTableRows(): array
    {
        $startDate = Carbon::parse($this->filters['startDate'] ?? now()->startOfYear())->startOfDay();
        $endDate = Carbon::parse($this->filters['endDate'] ?? now())->endOfDay();
        $cityIds = $this->filters['city'] ?? null;
        $centerIdsFromFilter = $this->filters['center'] ?? null;

        $finalCenterIds = [];
        if (!empty($centerIdsFromFilter)) {
            $finalCenterIds = $centerIdsFromFilter;
        } elseif (!empty($cityIds)) {
            $finalCenterIds = Center::whereIn('city_id', $cityIds)->pluck('id')->toArray();
        } else {
            $finalCenterIds = Center::pluck('id')->toArray();
        }

        if (empty($finalCenterIds)) {
            return [];
        }

        $diffInDays = $endDate->diffInDays($startDate);
        $groupByDay = $diffInDays <= 31;
        $dateFormat = $groupByDay ? '%Y-%m-%d' : '%Y-%m';

        $entries = DB::table('student_entries')
            ->select(
                DB::raw("DATE_FORMAT(entry_time, '{$dateFormat}') as period"),
                'center_id',
                DB::raw('COUNT(id) as count')
            )
            ->whereBetween('entry_time', [$startDate, $endDate])
            ->whereIn('center_id', $finalCenterIds)
            ->groupBy('period', 'center_id')
            ->orderBy('period')
            ->get();

        $pivotedData = [];
        foreach ($entries as $entry) {
            if (!isset($pivotedData[$entry->period])) {
                $pivotedData[$entry->period] = ['period' => $entry->period];
            }
            $pivotedData[$entry->period]['center_' . $entry->center_id] = $entry->count;
        }

        foreach ($pivotedData as $period => &$row) {
            foreach ($finalCenterIds as $centerId) {
                if (!isset($row['center_' . $centerId])) {
                    $row['center_' . $centerId] = 0;
                }
            }
        }

        return array_values($pivotedData);
    }
}
