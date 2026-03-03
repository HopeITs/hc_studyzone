<?php

namespace App\Filament\Widgets;

use App\Enums\Gender;
use App\Models\StudentEducation;
use Carbon\Carbon;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TopLevelsEntryExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class TopLevelsEntriesTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 8;
    protected static ?string $heading = 'Top Levels by Entries';

    /**
     * Fixes the "Return value must be of type string, null returned" error
     * by using the 'level' value as the unique row key.
     */
    public function getTableRecordKey(Model $record): string
    {
        return (string) $record->level;
    }

    public function getTable(): Table
    {
        return $this->table($this->makeTable());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getQuery())
            ->columns([
                TextColumn::make('level')
                    ->formatStateUsing(fn($state) => __("general.edu-levels.{$state}"))
                    ->label('Level'),
                TextColumn::make('total')->label('Total Entries'),
                TextColumn::make('males')->label('Males'),
                TextColumn::make('females')->label('Females'),
                TextColumn::make('unknown')->label('Unknown'),
            ])
            ->paginated(false)
            ->headerActions([
                Action::make('export')
                    ->label('Export to Excel')
                    ->color('success')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(function () {
                        return Excel::download(
                            new TopLevelsEntryExport($this->filters), 
                            'top_levels_by_entries.xlsx'
                        );
                    }),
            ]);
    }

    protected function getQuery(): Builder
    {
        $startDate = !is_null($this->filters['startDate'] ?? null)
            ? Carbon::parse($this->filters['startDate'])->startOfDay()
            : now()->startOfYear()->startOfDay();

        $endDate = !is_null($this->filters['endDate'] ?? null)
            ? Carbon::parse($this->filters['endDate'])->endOfDay()
            : now()->endOfMonth()->endOfDay();

        $cityIds = $this->filters['city'] ?? null;
        $centerIds = $this->filters['center'] ?? null;

        return StudentEducation::query()
            ->select('student_educations.level')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN gender = ? THEN 1 ELSE 0 END) as males', [Gender::Male()])
            ->selectRaw('SUM(CASE WHEN gender = ? THEN 1 ELSE 0 END) as females', [Gender::Female()])
            ->selectRaw('SUM(CASE WHEN gender = ? THEN 1 ELSE 0 END) as unknown', [Gender::Unknown()])
            ->whereNull('student_educations.end_date')
            ->join('students', 'student_educations.student_id', '=', 'students.id')
            ->join('student_entries', 'students.id', '=', 'student_entries.student_id')
            ->whereBetween('student_entries.entry_time', [$startDate, $endDate])
            ->when($cityIds, function ($q) use ($cityIds) {
                return $q->whereIn('students.street_id', function ($sub) use ($cityIds) {
                    $sub->select('id')->from('streets')->whereIn('city_id', (array)$cityIds);
                });
            })
            ->when($centerIds, fn($q) => $q->whereIn('student_entries.center_id', (array)$centerIds))
            ->groupBy('student_educations.level')
            ->orderByDesc('total')
            ->limit(10);
    }
}