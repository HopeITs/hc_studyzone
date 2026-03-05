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

class TopEducationsTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Top 10 Educations by Entries';
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

    $statsSubquery = DB::table('student_educations')
        // 1. Join entries where the entry_time happened DURING the education period
        ->join('student_entries', function ($join) {
            $join->on('student_educations.student_id', '=', 'student_entries.student_id')
                ->whereRaw('entry_time BETWEEN start_date AND COALESCE(end_date, NOW())');
        })
        ->join('students', 'students.id', '=', 'student_educations.student_id')
        ->select('student_educations.education_id')
        ->selectRaw('COUNT(*) as total')
        ->selectRaw('SUM(CASE WHEN gender = 1 THEN 1 ELSE 0 END) as males')
        ->selectRaw('SUM(CASE WHEN gender = 2 THEN 1 ELSE 0 END) as females')
        ->selectRaw('SUM(CASE WHEN gender = 0 THEN 1 ELSE 0 END) as unknown')
        // 2. Filter by the Dashboard date filters
        ->whereBetween('student_entries.entry_time', [$startDate, $endDate])
        ->groupBy('student_educations.education_id');

    // Apply Location/Center Filters
    if ($cityIds) {
        $statsSubquery->whereIn('students.street_id', function ($sub) use ($cityIds) {
            $sub->select('id')->from('streets')->whereIn('city_id', (array)$cityIds);
        });
    }

    if ($centerIds) {
        $statsSubquery->whereIn('student_entries.center_id', (array)$centerIds);
    }

    return Education::query()
        ->leftJoinSub($statsSubquery, 'stats', 'educations.id', '=', 'stats.education_id')
->select('educations.id', 'educations.name')
        ->selectRaw('COALESCE(total, 0) as total')
        ->selectRaw('COALESCE(males, 0) as males')
        ->selectRaw('COALESCE(females, 0) as females')
        ->selectRaw('COALESCE(unknown, 0) as unknown')
        ->orderByDesc('total')
        ->limit(10);
}
}
