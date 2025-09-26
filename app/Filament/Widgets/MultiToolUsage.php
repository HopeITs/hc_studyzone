<?php

namespace App\Filament\Widgets;

use App\Models\Center;
use App\Models\Student;
use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Illuminate\Support\Facades\DB;

class MultiToolUsage extends BaseWidget
{
    use InteractsWithPageFilters;

    protected function getCards(): array
    {
        $startDate = !is_null($this->filters['startDate'] ?? null)
            ? Carbon::parse($this->filters['startDate'])->startOfMonth()
            : now()->startOfYear();

        $endDate = !is_null($this->filters['endDate'] ?? null)
            ? Carbon::parse($this->filters['endDate'])->endOfMonth()
            : now()->endOfMonth();

        $centerIds = $this->filters['center'] ?? null;
        $cityIds = $this->filters['city'] ?? null;

        // Base query with applied filters
        $baseQuery = StudentEntry::query()
            ->whereBetween('entry_time', [$startDate, $endDate])
            ->when($centerIds, fn($q) => $q->whereIn('center_id', (array)$centerIds))
            ->when($cityIds, fn($q) => $q->whereHas('center', fn($q) => $q->whereIn('city_id', (array)$cityIds)));

        $totalEntries = (clone $baseQuery)->count();

        $multiToolUsers = (clone $baseQuery)
            ->whereRaw('(use_printer + use_drawer + use_mecrator) >= 2')
            ->count();

        $multiToolPercentage = $totalEntries > 0
            ? round(($multiToolUsers / $totalEntries) * 100, 2)
            : 0;

        $studentsWithMultipleCenters = StudentEntry::query()
            ->whereBetween('entry_time', [$startDate, $endDate])
            ->when($cityIds, fn($q) => $q->whereHas('center', fn($q) => $q->whereIn('city_id', (array)$cityIds)))
            ->select('student_id', DB::raw('COUNT(DISTINCT center_id) as center_count'))
            ->groupBy('student_id')
            ->having('center_count', '>', 1)
            ->get()
            ->count();
        $studentBaseQuery = Student::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when(!empty($cityIds), function ($query) use ($cityIds) {
                $query->whereHas('street', function ($q) use ($cityIds) {
                    $q->whereIn('city_id', (array) $cityIds);
                });
            })
            ->when(!empty($centerIds), function ($query) use ($centerIds) {
                $cities =   Center::whereIn('id', $centerIds)->distinct()->pluck('city_id')->toArray();

                $query->whereHas('street', function ($q) use ($cities) {
                    $q->whereIn('city_id', (array) $cities);
                });
            });
        // Total unique students within the filtered data
        $studentIds = (clone $studentBaseQuery)->pluck('id');


        $totalStudents = $studentIds->count();

// Count Architecture students (education_id = 65)
        $architectureCount = DB::table('student_educations')
            ->whereIn('student_id', $studentIds)
            ->where('education_id', 65)
            ->distinct('student_id')
            ->count();

// Count Dentistry students (education_id = 77 or 136)
        $dentistryCount = DB::table('student_educations')
            ->whereIn('student_id', $studentIds)
            ->whereIn('education_id', [77, 136])
            ->distinct('student_id')
            ->count();

// Calculate percentages
        $architecturePercentage = $totalStudents > 0
            ? round(($architectureCount / $totalStudents) * 100, 2)
            : 0;

        $dentistryPercentage = $totalStudents > 0
            ? round(($dentistryCount / $totalStudents) * 100, 2)
            : 0;

        return [
//            Card::make('استخدام أكثر من خدمة', "{$multiToolPercentage}%")
//                ->description('من الطلاب استخدموا أكثر من خدمة في الزيارة')
//                ->color($multiToolPercentage >= 50 ? 'success' : 'warning')
//                ->icon('heroicon-o-adjustments-horizontal'),

            Card::make('طلاب زاروا أكثر من مركز', $studentsWithMultipleCenters)
                ->description('عدد الطلاب الذين زاروا أكثر من مركز في المدينة المحددة')
                ->color('info')
                ->icon('heroicon-o-map-pin'),

            Card::make('طلاب هندسة العمارة', "{$architecturePercentage}%")
                ->description('نسبة طلاب هندسة العمارة من إجمالي الطلاب')
                ->color('success')
                ->icon('heroicon-o-academic-cap'),

            Card::make('طلاب طب الأسنان', "{$dentistryPercentage}%")
                ->description('نسبة طلاب طب الأسنان من إجمالي الطلاب')
                ->color('success')
                ->icon('heroicon-o-academic-cap'),
        ];
    }
}
