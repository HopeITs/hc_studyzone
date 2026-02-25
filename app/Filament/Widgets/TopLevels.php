<?php

namespace App\Filament\Widgets;

use App\Enums\Gender;
use App\Exports\TopLevelsExport;
use App\Models\Student;
use App\Models\StudentEducation;
use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;
use Maatwebsite\Excel\Facades\Excel;

class TopLevels extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 7;
    protected static ?string $heading = 'Top Education Levels by Student Entries';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getQuery())
            ->columns([
                Tables\Columns\TextColumn::make('level')
                    ->formatStateUsing(fn($state) => __('general.edu-levels.' . $state))
                    ->label('Education Level'),
                Tables\Columns\TextColumn::make('total')->label('Total Entries'),
                Tables\Columns\TextColumn::make('males')->label('Male Entries'),
                Tables\Columns\TextColumn::make('females')->label('Female Entries'),
                Tables\Columns\TextColumn::make('unknown')->label('Unknown Gender Entries'),
            ])
            ->paginated(false)
            ->headerActions([
                Action::make('export')
                    ->label('Export to Excel')
                    ->color('success')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(function () {
                        return Excel::download(new TopLevelsExport(
                            $this->filters
                        ), 'top_levels.xlsx');
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

        $entriesTable = (new StudentEntry())->getTable();
        $studentsTable = (new Student())->getTable();
        $studentEducationsTable = (new StudentEducation())->getTable();

        return StudentEducation::query()
            ->select("$studentEducationsTable.level")
            ->selectRaw("COUNT(DISTINCT $entriesTable.id) as total")
            ->selectRaw("SUM(CASE WHEN $studentsTable.gender = 1 THEN 1 ELSE 0 END) as males")
            ->selectRaw("SUM(CASE WHEN $studentsTable.gender = 2 THEN 1 ELSE 0 END) as females")
            ->selectRaw("SUM(CASE WHEN $studentsTable.gender = 0 THEN 1 ELSE 0 END) as unknown")
            ->join($studentsTable, "$studentsTable.id", '=', "$studentEducationsTable.student_id")
            ->join($entriesTable, function ($join) use ($entriesTable, $studentsTable, $studentEducationsTable) {
                $join->on("$studentsTable.id", '=', "$entriesTable.student_id")
                    ->whereRaw("$entriesTable.entry_time BETWEEN $studentEducationsTable.start_date AND COALESCE($studentEducationsTable.end_date, $entriesTable.entry_time)");
            })
            ->whereBetween("$entriesTable.entry_time", [$startDate, $endDate])
            ->when($cityIds, function ($query) use ($cityIds, $studentsTable) {
                $query->whereHas('student.street', function ($q) use ($cityIds) {
                    $q->whereIn('city_id', (array)$cityIds);
                });
            })
            ->when($centerIds, function ($query) use ($centerIds, $entriesTable) {
                $query->whereIn("$entriesTable.center_id", (array)$centerIds);
            })
            ->groupBy("$studentEducationsTable.level")
            ->orderByDesc('total')
            ->limit(10);
    }
}