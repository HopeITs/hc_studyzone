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
use Illuminate\Support\Collection;
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
        return Center::query()->where('id', -1);
    }

    public function table(Table $table): Table
    {
        $startDate = Carbon::parse($this->filters['startDate'] ?? now()->startOfYear());
        $endDate = Carbon::parse($this->filters['endDate'] ?? now());
        $centerIds = $this->filters['center'] ?? null;

        $diffInDays = $endDate->diffInDays($startDate);
        $groupByDay = $diffInDays <= 31;

        $centers = $centerIds ? Center::whereIn('id', $centerIds)->get() : Center::all();

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

    protected function getTableRows(): Collection
    {
        $startDate = Carbon::parse($this->filters['startDate'] ?? now()->startOfYear())->startOfDay();
        $endDate = Carbon::parse($this->filters['endDate'] ?? now())->endOfDay();
        $centerIds = $this->filters['center'] ?? null;

        if (empty($centerIds)) {
            $centerIds = Center::pluck('id')->toArray();
            if (empty($centerIds)) {
                return collect();
            }
        }

        $diffInDays = $endDate->diffInDays($startDate);
        $groupByDay = $diffInDays <= 31;
        $dbDateFormat = $groupByDay ? '%Y-%m-%d' : '%Y-%m';
        $phpDateFormat = $groupByDay ? 'Y-m-d' : 'Y-m';

        $pivotedData = [];
        $periodIterator = $startDate->clone();
        while ($periodIterator->lte($endDate)) {
            $periodKey = $periodIterator->format($phpDateFormat);

            if (!isset($pivotedData[$periodKey])) {
                $row = ['period' => $periodKey];
                foreach ($centerIds as $centerId) {
                    $row['center_' . $centerId] = 0;
                }
                $pivotedData[$periodKey] = $row;
            }

            if ($groupByDay) {
                $periodIterator->addDay();
            } else {
                $periodIterator->addMonthNoOverflow()->startOfMonth();
            }
        }

        $entries = DB::table('student_entries')
            ->select(
                DB::raw("DATE_FORMAT(entry_time, '{$dbDateFormat}') as period"),
                'center_id',
                DB::raw('COUNT(id) as count')
            )
            ->whereBetween('entry_time', [$startDate, $endDate])
            ->whereIn('center_id', $centerIds)
            ->groupBy('period', 'center_id')
            ->get();

        foreach ($entries as $entry) {
            if (isset($pivotedData[$entry->period])) {
                $pivotedData[$entry->period]['center_' . $entry->center_id] = $entry->count;
            }
        }

        return collect(array_values($pivotedData));
    }
}

