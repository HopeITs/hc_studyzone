<?php

namespace App\Filament\Widgets;

use App\Enums\Roles;
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
use Illuminate\Support\Facades\DB;

class TopEducationsStudentsEntriesTable extends TableWidget
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

    // Use a base subquery to avoid repeating joins and filters 4 times
    $statsSubquery = DB::table('student_educations')
        ->whereNull('end_date')
        ->join('students', 'students.id', '=', 'student_educations.student_id')
        ->join('student_entries', 'students.id', '=', 'student_entries.student_id')
        ->select('student_educations.education_id')
        ->selectRaw('COUNT(DISTINCT hcsz_students.id) as total')
        ->selectRaw('COUNT(DISTINCT CASE WHEN gender = 1 THEN hcsz_students.id END) as males')
        ->selectRaw('COUNT(DISTINCT CASE WHEN gender = 2 THEN hcsz_students.id END) as females')
        ->selectRaw('COUNT(DISTINCT CASE WHEN gender = 0 THEN hcsz_students.id END) as unknown')
        ->whereBetween('student_entries.entry_time', [$startDate, $endDate])
        ->groupBy('student_educations.education_id');

    // Apply Filters to the subquery
    if ($cityIds) {
        $statsSubquery->whereIn('students.street_id', function ($sub) use ($cityIds) {
            $sub->select('id')->from('streets')->whereIn('city_id', (array)$cityIds);
        });
    }

    if ($centerIds) {
        $statsSubquery->whereIn('student_entries.center_id', (array)$centerIds);
    }

    // Join the main Education model with our stats
    return Education::query()
        ->leftJoinSub($statsSubquery, 'stats', function ($join) {
            $join->on('educations.id', '=', 'stats.education_id');
        })
        ->select('educations.*')
        ->selectRaw('COALESCE(total, 0) as total')
        ->selectRaw('COALESCE(males, 0) as males')
        ->selectRaw('COALESCE(females, 0) as females')
        ->selectRaw('COALESCE(unknown, 0) as unknown')
        ->orderByDesc('total')
        ->limit(10);
}
}
