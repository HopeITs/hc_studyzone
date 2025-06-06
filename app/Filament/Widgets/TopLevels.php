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
use App\Exports\TopLevelsExport;

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
                    ->formatStateUsing(fn($state) => __('general.edu-levels.' . $state))
                    ->label('Level'),
                TextColumn::make('total')->label('Total'),
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
            : now()->startOfMonth()->startOfDay();

        $endDate = !is_null($this->filters['endDate'] ?? null)
            ? Carbon::parse($this->filters['endDate'])->endOfDay()
            : now()->endOfMonth()->endOfDay();

        $cityIds = $this->filters['city'] ?? null;
        $centerIds = $this->filters['center'] ?? null;

        return StudentEducation::query()
            ->selectRaw('
                ROW_NUMBER() OVER (ORDER BY COUNT(*) DESC) as id,
                level,
                COUNT(*) as total,
                SUM(CASE WHEN gender = ? THEN 1 ELSE 0 END) as males,
                SUM(CASE WHEN gender = ? THEN 1 ELSE 0 END) as females,
                SUM(CASE WHEN gender = ? THEN 1 ELSE 0 END) as unknown
            ', [Gender::Male(), Gender::Female(), Gender::Unknown()])
            ->join('students', 'student_educations.student_id', '=', 'students.id')
            ->when($startDate, fn($q) => $q->where('students.created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('students.created_at', '<=', $endDate))
            ->when($cityIds, fn($q) => $q->whereHas('student.street', fn($s) => $s->whereIn('city_id', $cityIds)))
            ->when($centerIds, fn($q) => $q->whereHas('student.entries', fn($e) => $e->whereIn('center_id', $centerIds)))
            ->groupBy('level')
            ->having('total', '>', 0)
            ->orderByDesc('total')
            ->limit(10);
    }
}
