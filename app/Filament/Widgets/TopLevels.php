<?php

namespace App\Filament\Widgets;


use App\Enums\Gender;
use App\Models\StudentEducation;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;


class TopLevels extends TableWidget
{

    use InteractsWithPageFilters;

    protected static ?int $sort = 7;
    protected static ?string $heading = 'Top 10 Levels by Students';

    public function table(Table $table): Table
    {

        return $table
            ->query($this->getQuery())
            ->columns([
                TextColumn::make('level')
                    ->formatStateUsing(function ($state) {
                        return __('general.edu-levels.' . $state);
                    })
                    ->label('Level'),
                TextColumn::make('total')
                    ->label('Total'),
                TextColumn::make('males')
                    ->label('Males'),
                TextColumn::make('females')
                    ->label('Females'),
                TextColumn::make('unknown')
                    ->label('unknown'),
            ])
            ->paginated(false);
    }

    protected function getQuery(): Builder
    {

        $startDate = !is_null($this->filters['startDate'] ?? null)
            ? Carbon::parse($this->filters['startDate'])->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $endDate = !is_null($this->filters['endDate'] ?? null)
            ? Carbon::parse($this->filters['endDate'])->endOfDay()
            : now()->endOfMonth()->endOfDay();
        $cityIds = $this->filters['city'] ?? null;
        $centerIds = $this->filters['center'] ?? null;

        return

            StudentEducation::query()
                ->selectRaw('
            ROW_NUMBER() OVER (ORDER BY COUNT(*) DESC) as id,
            level,
            COUNT(*) as total,
            SUM(CASE WHEN gender = ? THEN 1 ELSE 0 END) as males,
            SUM(CASE WHEN gender = ? THEN 1 ELSE 0 END) as females,
            SUM(CASE WHEN gender = ? THEN 1 ELSE 0 END) as unknown
        ', [Gender::Male(), Gender::Female() , Gender::Unknown()])
                ->join('students', 'student_educations.student_id', '=', 'students.id')
                ->when($startDate, fn($query) => $query->where('students.created_at', '>=', $startDate))
                ->when($endDate, fn($query) => $query->where('students.created_at', '<=', $endDate))
                ->when($cityIds, fn($query) => $query->whereHas('student', function ($sQuery) use ($cityIds) {
                    $sQuery->whereHas('street', fn($q) => $q->whereIn('city_id', $cityIds));
                }))
                ->when($centerIds, fn($query) => $query->whereHas('student', function ($sQuery) use ($centerIds) {
                    $sQuery->whereHas('entries', fn($q) => $q->whereIn('center_id', $centerIds));
                }))
                ->groupBy('level')
                ->having('total', '>', 0)
                ->orderByDesc('total')
                ->limit(10);
    }

}
