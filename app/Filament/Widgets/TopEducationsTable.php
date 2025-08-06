<?php

namespace App\Filament\Widgets;

use App\Exports\EducationExport;
use App\Models\Education;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;
use Maatwebsite\Excel\Facades\Excel;

class TopEducationsTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Top 10 Educations by Students';
    protected static ?int $sort = 7;

    public function getTable(): Table
    {
        return $this->table($this->makeTable());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Education'),
                Tables\Columns\TextColumn::make('total')->label('Total'),
                Tables\Columns\TextColumn::make('males')->label('Males'),
                Tables\Columns\TextColumn::make('females')->label('Females'),
                Tables\Columns\TextColumn::make('unknown')->label('Unknown'),
            ])
            ->paginated(false)
            ->headerActions([
                Action::make('export')
                    ->label('Export to Excel')
                    ->color('success')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(function () {
                        return Excel::download(new EducationExport(
                            $this->filters
                        ), 'top_educations.xlsx');
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

        $applyCityFilter = function ($query) use ($cityIds) {
            if ($cityIds) {
                $query->whereIn('students.street_id', function ($sub) use ($cityIds) {
                    $sub->select('id')
                        ->from('streets')
                        ->whereIn('city_id', (array)$cityIds);
                });
            }
        };

        $applyCenterFilter = function ($query) use ($centerIds) {
            if ($centerIds) {
                $query->whereIn('students.id', function ($sub) use ($centerIds) {
                    $sub->select('student_id')
                        ->from('student_entries')
                        ->whereIn('center_id', (array)$centerIds);
                });
            }
        };

        return Education::query()
            ->select('educations.*')
            ->selectSub(function ($query) use ($startDate, $endDate, $applyCityFilter, $applyCenterFilter) {
                $query->from('student_educations')
                    ->join('students', 'students.id', '=', 'student_educations.student_id')
                    ->selectRaw('count(*)')
                    ->whereColumn('student_educations.education_id', 'educations.id')
                    ->whereBetween('students.created_at', [$startDate, $endDate]);

                $applyCityFilter($query);
                $applyCenterFilter($query);
            }, 'total')
            ->selectSub(function ($query) use ($startDate, $endDate, $applyCityFilter, $applyCenterFilter) {
                $query->from('student_educations')
                    ->join('students', 'students.id', '=', 'student_educations.student_id')
                    ->selectRaw('count(*)')
                    ->whereColumn('student_educations.education_id', 'educations.id')
                    ->where('students.gender', 1)
                    ->whereBetween('students.created_at', [$startDate, $endDate]);

                $applyCityFilter($query);
                $applyCenterFilter($query);
            }, 'males')
            ->selectSub(function ($query) use ($startDate, $endDate, $applyCityFilter, $applyCenterFilter) {
                $query->from('student_educations')
                    ->join('students', 'students.id', '=', 'student_educations.student_id')
                    ->selectRaw('count(*)')
                    ->whereColumn('student_educations.education_id', 'educations.id')
                    ->where('students.gender', 2)
                    ->whereBetween('students.created_at', [$startDate, $endDate]);

                $applyCityFilter($query);
                $applyCenterFilter($query);
            }, 'females')
            ->selectSub(function ($query) use ($startDate, $endDate, $applyCityFilter, $applyCenterFilter) {
                $query->from('student_educations')
                    ->join('students', 'students.id', '=', 'student_educations.student_id')
                    ->selectRaw('count(*)')
                    ->whereColumn('student_educations.education_id', 'educations.id')
                    ->where('students.gender', 0)
                    ->whereBetween('students.created_at', [$startDate, $endDate]);

                $applyCityFilter($query);
                $applyCenterFilter($query);
            }, 'unknown')
            ->orderByDesc('total')
            ->limit(10);
    }
}
