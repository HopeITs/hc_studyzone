<?php

namespace App\Filament\Widgets;

use App\Enums\Gender;
use App\Models\StudentEducation;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class LatestOrders extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 7;

    public function table(Table $table): Table
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $cityIds = $this->filters['city'] ?? null;
        $centerIds = $this->filters['center'] ?? null;

        $query = StudentEducation::query()
            ->join('students', 'students.id', '=', 'student_educations.student_id')
            ->when($startDate, fn($q) => $q->whereDate('students.created_at', '>=', Carbon::parse($startDate)))
            ->when($endDate, fn($q) => $q->whereDate('students.created_at', '<=', Carbon::parse($endDate)))
            ->when($cityIds, fn($q) => $q->whereIn('students.city_id', (array)$cityIds))
            ->when($centerIds, fn($q) => $q->whereExists(function ($sub) use ($centerIds) {
                $sub->select(DB::raw(1))
                    ->from('student_entries')
                    ->whereColumn('student_entries.student_id', 'students.id')
                    ->whereIn('student_entries.center_id', (array)$centerIds);
            }))
            ->select('student_educations.level', DB::raw('COUNT(*) as students_count'))
            ->groupBy('student_educations.level');

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('level')
                    ->label('السنة الدراسية')
                    ->formatStateUsing(fn($state) => __('general.edu-levels.' . $state)),

                TextColumn::make('students_count')
                    ->label('عدد الطلاب')
                    ->getStateUsing(fn($record) => $record->students_count),

                TextColumn::make('male_students')
                    ->label('عدد الذكور')
                    ->getStateUsing(fn($record) =>
                    StudentEducation::where('level', $record->level)
                        ->whereHas('student', fn($q) => $q->where('gender', Gender::Male))
                        ->count()
                    ),

                TextColumn::make('female_students')
                    ->label('عدد الإناث')
                    ->getStateUsing(fn($record) =>
                    StudentEducation::where('level', $record->level)
                        ->whereHas('student', fn($q) => $q->where('gender', Gender::Female))
                        ->count()
                    ),
            ]);
    }

    public function getTableRecordKey(mixed $record): string
    {
        return (string)$record->level;
    }
}
