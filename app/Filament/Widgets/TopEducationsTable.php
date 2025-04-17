<?php
namespace App\Filament\Widgets;

use App\Models\Education;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TopEducationsTable extends TableWidget
{
    use InteractsWithPageFilters;
    protected static ?string $heading = 'Top 10 Educations by Students';
//    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 7;


    public function table(Table $table): Table
    {
        return $table
            ->query($this->getQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Education'),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total'),
                Tables\Columns\TextColumn::make('males')
                    ->label('Males'),
                Tables\Columns\TextColumn::make('females')
                    ->label('Females'),
                Tables\Columns\TextColumn::make('unknown')
                    ->label('Unknown'),
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

        return Education::query()
            ->withCount([
                'students as total' => function ($query) use ($startDate, $endDate, $cityIds,$centerIds) {
                    $query->whereBetween('students.created_at', [$startDate, $endDate]);
                    if ($cityIds) {
                        $query->whereHas('street', fn($q) => $q->whereIn('city_id', (array)$cityIds));
                    }
                    if($centerIds) {
                        $query->whereHas('entries', fn($q) => $q->whereIn('center_id', (array)$centerIds));
                    }
                },
                'students as males' => function ($query) use ($startDate, $endDate, $cityIds) {
                    $query->where('students.gender', 1)
                          ->whereBetween('students.created_at', [$startDate, $endDate]);
                    if ($cityIds) {
                        $query->whereHas('street', fn($q) => $q->whereIn('city_id', (array)$cityIds));
                    }
                },
                'students as females' => function ($query) use ($startDate, $endDate, $cityIds) {
                    $query->where('students.gender', 2)
                          ->whereBetween('students.created_at', [$startDate, $endDate]);
                    if ($cityIds) {
                        $query->whereHas('street', fn($q) => $q->whereIn('city_id', (array)$cityIds));
                    }
                },
                'students as unknown' => function ($query) use ($startDate, $endDate, $cityIds) {
                    $query->where('students.gender', 0)
                        ->whereBetween('students.created_at', [$startDate, $endDate]);
                    if ($cityIds) {
                        $query->whereHas('street', fn($q) => $q->whereIn('city_id', (array)$cityIds));
                    }
                },
            ])
            ->having('total', '>', 0)
            ->orderByDesc('total')
            ->limit(10);
    }
}
