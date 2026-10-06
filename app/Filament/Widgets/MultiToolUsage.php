<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Center;
use App\Models\Student;
use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class MultiToolUsage extends BaseWidget
{
    use InteractsWithPageFilters;

    protected function getCards(): array
    {
        $startDate = !is_null($this->pageFilters['startDate'] ?? null)
            ? Carbon::parse($this->pageFilters['startDate'])->startOfMonth()
            : now()->startOfYear();

        $endDate = !is_null($this->pageFilters['endDate'] ?? null)
            ? Carbon::parse($this->pageFilters['endDate'])->endOfMonth()
            : now()->endOfMonth();

        $centerIds = $this->pageFilters['center'] ?? null;
        $cityIds = $this->pageFilters['city'] ?? null;

        // Base query with applied filters
        $baseQuery = StudentEntry::query()
            ->whereBetween('entry_time', [$startDate, $endDate])
            ->when($centerIds, fn($q) => $q->whereIn('center_id', (array)$centerIds))
            ->when($cityIds, fn($q) => $q->whereHas('center', fn($q) => $q->whereIn('city_id', (array)$cityIds)));

        $totalEntries = (clone $baseQuery)->count();

        $multiToolUsers = (clone $baseQuery)
            ->whereRaw('(use_printer + use_drawer + use_mecrator + use_internet) >= 2')
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
            ->whereIn('education_id', [25, 77,111, 136, 221, 223, 224, 225, 226, 227, 228])
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
//            Card::make(__('general.labels.more_than_one_service_usage'), "{$multiToolPercentage}%")
//                ->description(__('general.labels.more_than_one_service_usage_description'))
//                ->color($multiToolPercentage >= 50 ? 'success' : 'warning')
//                ->icon('heroicon-o-adjustments-horizontal'),

            Stat::make(__('general.labels.students_visited_multiple_centers'), $studentsWithMultipleCenters)
                ->description(__('general.labels.students_visited_multiple_centers_description'))
                ->color('info')
                ->icon('heroicon-o-map-pin'),

            Stat::make(__('general.labels.architecture_students'), "{$architecturePercentage}%")
                ->description(__('general.labels.architecture_students_description'))
                ->color('success')
                ->icon('heroicon-o-academic-cap'),

            Stat::make(__('general.labels.dentistry_students'), "{$dentistryPercentage}%")
                ->description(__('general.labels.dentistry_students_description'))
                ->color('success')
                ->icon('heroicon-o-academic-cap'),
        ];
    }
}
