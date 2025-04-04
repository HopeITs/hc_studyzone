<?php
//
//namespace App\Filament\Widgets;
//
//use App\Models\StudentEducation;
//use Carbon\Carbon;
//use Filament\Support\Contracts\TranslatableContentDriver;
//use Filament\Tables;
//use Filament\Tables\Concerns\InteractsWithTable;
//use Filament\Tables\Contracts\HasTable;
//use Filament\Tables\Table;
//use Filament\Widgets\Widget;
//use Illuminate\Support\Collection;
//
//class TopEducationsTable extends Widget implements HasTable
//{
//    use InteractsWithTable;
//
//    protected static ?string $heading = 'Top 10 Educations by Students';
//    protected static string $view = 'filament.widgets.card'; // أو أي view إن كنت مخصص
//    protected int|string|array $columnSpan = 'full';
//
//    public function getTableRecords(): \Illuminate\Contracts\Pagination\CursorPaginator|\Illuminate\Contracts\Pagination\Paginator|\Illuminate\Database\Eloquent\Collection
//    {
//        $startDate = !is_null($this->filters['startDate'] ?? null)
//            ? Carbon::parse($this->filters['startDate'])->startOfDay()
//            : now()->startOfMonth()->startOfDay();
//
//        $endDate = !is_null($this->filters['endDate'] ?? null)
//            ? Carbon::parse($this->filters['endDate'])->endOfDay()
//            : now()->endOfMonth()->endOfDay();
//
//        $cityIds = $this->filters['city'] ?? null;
//
//        $records = StudentEducation::with(['student.street', 'education'])
//            ->whereHas('student', function ($q) use ($startDate, $endDate, $cityIds) {
//                $q->whereBetween('created_at', [$startDate, $endDate]);
//
//                if ($cityIds) {
//                    $q->whereHas('street', fn($q2) => $q2->whereIn('city_id', (array)$cityIds));
//                }
//            })
//            ->get();
//
//        return $records
//            ->groupBy('education_id')
//            ->map(function ($group) {
//                $education = $group->first()->education;
//                $students = $group->pluck('student');
//
//                return (object)[
//                    'education' => $education,
//                    'total' => $students->count(),
//                    'males' => $students->where('gender', 1)->count(),
//                    'females' => $students->where('gender', 2)->count(),
//                ];
//            })
//            ->sortByDesc('total')
//            ->take(10)
//            ->values();
//    }
//
//    public function table(Table $table): Table
//    {
//        return $table
//            ->columns([
//                Tables\Columns\TextColumn::make('education.name.en')->label('Education'),
//                Tables\Columns\TextColumn::make('total')->label('Total'),
//                Tables\Columns\TextColumn::make('males')->label('Males'),
//                Tables\Columns\TextColumn::make('females')->label('Females'),
//            ])
//            ->paginated(false);
//    }
//
//    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
//    {
//        // TODO: Implement makeFilamentTranslatableContentDriver() method.
//    }
//}
