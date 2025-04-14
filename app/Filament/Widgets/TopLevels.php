<?php
//
//use App\Models\StudentEducation;
//use Carbon\Carbon;
//use Filament\Tables\Columns\TextColumn;
//use Filament\Tables\Table;
//use Filament\Widgets\Concerns\InteractsWithPageFilters;
//use Filament\Widgets\TableWidget as BaseWidget;
//use Illuminate\Support\Facades\DB;
//
//class TopLevels extends BaseWidget
//{
//    use InteractsWithPageFilters;
//
//    protected static ?string $heading = 'أكثر السنوات الدراسية للطلاب';
//    protected static ?int $sort = 6;
//    public function table(Table $table): Table
//    {
//        $startDate = $this->filters['startDate'] ?? null;
//        $endDate = $this->filters['endDate'] ?? null;
//        $cityIds = $this->filters['city'] ?? null;
//        $centerIds = $this->filters['center'] ?? null;
//
//        $query = StudentEducation::query()
//            ->join('students', 'students.id', '=', 'idstudent_id')
//            ->when($startDate, fn($q) => $q->whereDate('students.created_at', '>=', Carbon::parse($startDate))
//            )
//            ->when($endDate, fn($q) => $q->whereDate('students.created_at', '<=', Carbon::parse($endDate))
//            )
//            ->when($cityIds, fn($q) => $q->whereIn('students.city_id', (array)$cityIds)
//            )
//            ->when($centerIds, fn($q) => $q->whereExists(function ($sub) use ($centerIds) {
//                $sub->select(DB::raw(1))
//                    ->from('student_entries')
//                    ->whereColumn('student_entries.student_id', 'students.id')
//                    ->whereIn('student_entries.center_id', (array)$centerIds);
//            })
//            )
//            ->select(
//
//                'student_educations.level',
//                DB::raw('COUNT(DISTINCT student_educations.student_id) as total_students'),
//                DB::raw('COUNT(DISTINCT CASE WHEN students.gender = 0 THEN student_educations.student_id END) as male_students'),
//                DB::raw('COUNT(DISTINCT CASE WHEN students.gender = 1 THEN student_educations.student_id END) as female_students')
//            )
//            ->groupBy('student_educations.level')
//            ->orderByDesc('total_students')
//            ->limit(5);
//
//        return $table
//            ->query($query)
//            ->columns([
//                TextColumn::make('level')
//                    ->label('السنة الدراسية')
//                    ->formatStateUsing(fn($state) => __('levels.' . $state)),
//
//                TextColumn::make('total_students')
//                    ->label('عدد الطلاب')
//                    ->sortable(),
//
//                TextColumn::make('male_students')
//                    ->label('عدد الذكور')
//                    ->sortable(),
//
//                TextColumn::make('female_students')
//                    ->label('عدد الإناث')
//                    ->sortable(),
//            ]);
//    }
//
//    public function getTableRecordKey(mixed $record): string
//    {
//        return (string) $record->level;
//    }
//}
//
