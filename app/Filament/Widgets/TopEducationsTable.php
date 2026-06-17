<?php

namespace App\Filament\Widgets;

use App\Enums\Roles;
use App\Exports\TopEducationsByEntriesExport;
use App\Models\Education;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class TopEducationsTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = null;
    protected static ?int $sort = 7;

    public function getTableHeading(): string
    {
        return __('general.labels.top_educations_by_entries');
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
                Tables\Columns\TextColumn::make('name')
                    ->label(__('general.labels.education'))
                    ->formatStateUsing(fn($state) => is_string($state) ? 
                        (json_decode($state, true)['ar'] ?? $state) : $state),
                Tables\Columns\TextColumn::make('total')->label(__('general.labels.total')),
                Tables\Columns\TextColumn::make('males')->label(__('general.labels.males')),
                Tables\Columns\TextColumn::make('females')->label(__('general.labels.females')),
                Tables\Columns\TextColumn::make('unknown')->label(__('general.labels.unknown')),
            ])
            ->paginated(false)
            ->headerActions([
                Action::make('export')
                    ->label(__('general.labels.export_to_excel'))
                    ->color('success')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(function () {
                        return Excel::download(new TopEducationsByEntriesExport(
                            $this->filters
                        ), 'top_educations_by_entries.xlsx');
                    }),
            ]);
    }

    protected function getQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $startDate = $this->filters['startDate'] ?? now()->startOfYear()->startOfDay()->toDateTimeString();
        $endDate = $this->filters['endDate'] ?? now()->toDateTimeString();
        $cityIds = isset($this->filters['city']) ? implode(',', (array)$this->filters['city']) : null;
        $centerIds = isset($this->filters['center']) ? implode(',', (array)$this->filters['center']) : null;

        $cityCondition = $cityIds ? "AND s.street_id IN (SELECT id FROM hcsz_streets WHERE city_id IN ($cityIds))" : "";
        $centerCondition = $centerIds ? "AND sen.center_id IN ($centerIds)" : "";

        $rawSql = "
            SELECT 
                edu.id as id, 
                edu.name as name, 
                stats.total as total, 
                stats.males as males, 
                stats.females as females, 
                stats.unknown as unknown
            FROM hcsz_educations edu
            INNER JOIN (
                SELECT 
                    sed.education_id,
                    COUNT(DISTINCT sen.id) as total, 
                    COUNT(DISTINCT CASE WHEN s.gender = 1 THEN sen.id END) as males,
                    COUNT(DISTINCT CASE WHEN s.gender = 2 THEN sen.id END) as females,
                    COUNT(DISTINCT CASE WHEN s.gender = 0 THEN sen.id END) as unknown
                FROM hcsz_student_educations sed
                INNER JOIN hcsz_student_entries sen ON sed.student_id = sen.student_id 
                    AND ( DATE(sen.entry_time) >= sed.start_date)
                    AND (sed.end_date IS NULL OR  DATE(sen.entry_time) <= sed.end_date)
                LEFT JOIN hcsz_students AS s ON s.id = sed.student_id
                WHERE  DATE(sen.entry_time) BETWEEN '$startDate' AND '$endDate'
                $cityCondition
                $centerCondition
                GROUP BY sed.education_id
            ) AS stats ON edu.id = stats.education_id
       
        ";

        return \App\Models\Education::query()
            ->fromRaw("($rawSql) as top_stats")
            ->select(['id', 'name', 'total', 'males', 'females', 'unknown'])
            ->reorder() 
            ->orderByDesc('total')->take(10);
    }
}
