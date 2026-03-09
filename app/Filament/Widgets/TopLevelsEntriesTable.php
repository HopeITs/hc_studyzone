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
use App\Exports\TopLevelsByEntriesExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class TopLevelsEntriesTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 8;
    protected static ?string $heading = 'Top Levels by Entries';

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
                            new TopLevelsByEntriesExport($this->filters), 
                            'top_levels_by_entries.xlsx'
                        );
                    }),
            ]);
    }

    protected function getQuery(): Builder
    {
        $startDate = $this->filters['startDate'] ?? now()->startOfYear()->toDateTimeString();
        $endDate = $this->filters['endDate'] ?? now()->toDateTimeString();
        $cityIds = isset($this->filters['city']) ? implode(',', (array)$this->filters['city']) : null;
        $centerIds = isset($this->filters['center']) ? implode(',', (array)$this->filters['center']) : null;

        $cityCondition = $cityIds ? "AND s.street_id IN (SELECT id FROM hcsz_streets WHERE city_id IN ($cityIds))" : "";
        $centerCondition = $centerIds ? "AND sen.center_id IN ($centerIds)" : "";

        $rawSql = "
            SELECT 
                sed.level as id,
                sed.level,
                COUNT(DISTINCT sen.id) as total,
                COUNT(DISTINCT CASE WHEN s.gender = 1 THEN sen.id END) as males,
                COUNT(DISTINCT CASE WHEN s.gender = 2 THEN sen.id END) as females,
                COUNT(DISTINCT CASE WHEN s.gender = 0 THEN sen.id END) as unknown
            FROM hcsz_student_educations sed
            INNER JOIN hcsz_student_entries sen ON sed.student_id = sen.student_id 
                AND (DATE(sen.entry_time) >= DATE(sed.start_date))
                AND (sed.end_date IS NULL OR DATE(sen.entry_time) <= DATE(sed.end_date))
            LEFT JOIN hcsz_students AS s ON s.id = sed.student_id
            WHERE DATE(sen.entry_time) BETWEEN '$startDate' AND '$endDate'
            $cityCondition
            $centerCondition
            GROUP BY sed.level
            ORDER BY total DESC
            LIMIT 10
        ";

        return \App\Models\StudentEducation::query()
            ->fromRaw("($rawSql) as level_stats")
            ->select(['id', 'level', 'total', 'males', 'females', 'unknown'])
            ->reorder()
            ->orderByDesc('total');
    }
}