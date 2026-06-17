<?php

namespace App\Filament\Widgets;

use App\Enums\Gender;
use App\Models\StudentEducation;
use Carbon\Carbon;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TopLevelsExport;
use Illuminate\Support\Facades\DB;

class TopLevels extends BaseTableWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 7;
    protected static ?string $heading = null;

    public function getTableHeading(): string
    {
        return __('general.labels.top_levels_by_students');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getQuery())
            ->columns([
                TextColumn::make('level')
                    ->formatStateUsing(fn($state) => __('general.edu-levels.' . $state))
                    ->label(__('general.labels.level')),
                TextColumn::make('total')->label(__('general.labels.total')),
                TextColumn::make('males')->label(__('general.labels.males')),
                TextColumn::make('females')->label(__('general.labels.females')),
                TextColumn::make('unknown')->label(__('general.labels.unknown')),
            ])
            ->paginated(false)
            ->headerActions([
                Action::make('export')
                    ->label(__('general.labels.export_to_excel'))
                    ->color('success')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(function () {
                        return Excel::download(new TopLevelsExport(
                            $this->filters
                        ), 'top_levels.xlsx');
                    }),
            ]);
    }

protected function getQuery(): \Illuminate\Database\Eloquent\Builder
{
    $startDate = $this->filters['startDate'] ?? now()->startOfYear()->toDateTimeString();
    $endDate = $this->filters['endDate'] ?? now()->toDateTimeString();
    $cityIds = isset($this->filters['city']) ? implode(',', (array)$this->filters['city']) : null;
    $centerIds = isset($this->filters['center']) ? implode(',', (array)$this->filters['center']) : null;

    $cityCondition = $cityIds ? "AND s.street_id IN (SELECT id FROM hcsz_streets WHERE city_id IN ($cityIds))" : "";
    $centerCondition = $centerIds ? "AND sen.center_id IN ($centerIds)" : "";

    $rawSql = "
        SELECT 
            /* نستخدم level كـ ID فريد للجدول في Filament */
            sed.level as id, 
            sed.level, 
            COUNT(DISTINCT s.id) as total, 
            COUNT(DISTINCT CASE WHEN s.gender = 1 THEN s.id END) as males,
            COUNT(DISTINCT CASE WHEN s.gender = 2 THEN s.id END) as females,
            COUNT(DISTINCT CASE WHEN s.gender = 0 THEN s.id END) as unknown
        FROM hcsz_student_educations sed
        INNER JOIN hcsz_student_entries sen ON sed.student_id = sen.student_id 
            /* الشرط الزمني الجوهري: الدخول حصل أثناء التواجد في هذا المستوى */
            AND (DATE(sen.entry_time) >= DATE(sed.start_date))
            AND (sed.end_date IS NULL OR DATE(sen.entry_time) <= DATE(sed.end_date))
        LEFT JOIN hcsz_students AS s ON s.id = sed.student_id
        WHERE DATE(sen.entry_time) BETWEEN '$startDate' AND '$endDate'
        $cityCondition
        $centerCondition
        GROUP BY sed.level
    ";

    return \App\Models\StudentEducation::query()
        ->fromRaw("($rawSql) as level_stats")
        ->select(['id', 'level', 'total', 'males', 'females', 'unknown'])
        ->reorder() 
        ->orderByDesc('total');
}

  
}
