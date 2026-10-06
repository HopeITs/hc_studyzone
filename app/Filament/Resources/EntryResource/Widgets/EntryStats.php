<?php

namespace App\Filament\Resources\EntryResource\Widgets;

use App\Enums\EntryType;
use App\Filament\Resources\EntryResource\Pages\ListEntries;
use App\Models\EntryService;
use App\Models\Service;
use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class EntryStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected ?string $pollingInterval = null;

    protected function getTablePage(): string
    {
        return ListEntries::class;
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        // Get base query for entries to fetch IDs
        $entryIdsQuery = $this->getPageTableQuery()->select('id');

        // 1. Get all unique reference columns from services
        $referenceColumns = Service::whereNotNull('reference_column_name')
            ->pluck('reference_column_name')
            ->unique()
            ->filter()
            ->toArray();

        // 2. Calculate entry counts for each reference column using a single efficient query
        $counts = null;
        if (!empty($referenceColumns)) {
            $selects = [];
            foreach ($referenceColumns as $col) {
                $selects[] = "SUM(CASE WHEN `{$col}` = 1 THEN 1 ELSE 0 END) as `{$col}_count`";
            }

            $counts = StudentEntry::whereIn('id', $entryIdsQuery)
                ->selectRaw(implode(', ', $selects))
                ->first();
        }

        // 3. Calculate amounts and quantities from entry_services grouped by reference_column_name
        $serviceStats = EntryService::whereIn('entry_id', $entryIdsQuery)
            ->whereHas('service', fn ($q) => $q->whereNotNull('reference_column_name'))
            ->join('services', 'entry_services.service_id', '=', 'services.id')
            ->select(
                'services.reference_column_name',
                DB::raw('SUM(hcsz_entry_services.amount) as total_quantity'),
                DB::raw('SUM(hcsz_entry_services.total) as total_amount')
            )
            ->groupBy('services.reference_column_name')
            ->get()
            ->keyBy('reference_column_name');

        // 4. Build stats dynamically based on the reference columns
        $referenceStatArray = [];
        foreach ($referenceColumns as $col) {
            $entriesCount = (int) ($counts->{"{$col}_count"} ?? 0);
            $quantity = (int) ($serviceStats[$col]->total_quantity ?? 0);
            $amount = (int) ($serviceStats[$col]->total_amount ?? 0);

            $label = match($col) {
                'use_drawer' => __('general.labels.drawer'),
                'use_mecrator' => __('general.labels.mecrator'),
                'use_printer', 'use_pinter' => __('general.labels.printer'),
                'use_plotter' => __('general.labels.plotter'),
                'stationary' => __('general.labels.stationery'),
                'use_internet' => __('general.labels.internet'),
                'reservation' => __('general.labels.reservation_short'),
                default => ucfirst(str_replace(['use_', '_'], ['', ' '], $col)),
            };

            $referenceStatArray[] = Stat::make($label, $entriesCount)
                ->description(new HtmlString(
                    '<span class="donor-hide">' . e(__('general.labels.quantity_total_description', [
                        'quantity' => $quantity,
                        'total' => number_format($amount),
                    ])) . '</span>'
                ));
        }

        // Active days: distinct entry_time dates per center, and entries/day averaged across centers.
        // toBase() skips model hydration/eager loading (irrelevant for aggregate rows).
        $centerStats = $this->getPageTableQuery()
            ->reorder()
            ->toBase()
            ->selectRaw('center_id, COUNT(DISTINCT DATE(entry_time)) as active_days, COUNT(*) as total_entries')
            ->groupBy('center_id')
            ->get();

        $centerCount = $centerStats->count();
        $avgActiveDays = $centerCount > 0 ? $centerStats->avg('active_days') : 0;

        $rangeFrom = $this->tableFilters['entry_time']['from'] ?? null;
        $rangeTo = $this->tableFilters['entry_time']['to'] ?? null;
        $totalRangeDays = ($rangeFrom && $rangeTo)
            ? Carbon::parse($rangeFrom)->startOfDay()->diffInDays(Carbon::parse($rangeTo)->startOfDay(), true) + 1
            : null;

        $activeDaysDescription = $totalRangeDays !== null
            ? __('general.labels.active_days_out_of_total_description', ['total' => $totalRangeDays, 'count' => $centerCount])
            : __('general.labels.distributed_over_centers_description', ['count' => $centerCount]);

        // Total + distinct students in one pass instead of two separate counts
        $totals = $this->getPageTableQuery()
            ->reorder()
            ->toBase()
            ->selectRaw('COUNT(*) as total, COUNT(DISTINCT student_id) as students')
            ->first();

        // Entry-type breakdown in one grouped query instead of 7 separate counts
        $entryTypeCounts = $this->getPageTableQuery()
            ->reorder()
            ->toBase()
            ->selectRaw('entry_type, COUNT(*) as total')
            ->groupBy('entry_type')
            ->pluck('total', 'entry_type');

        $architectureEducationIds = [65, 176, 242];
        $dentistryEducationIds = [25, 77, 111, 136, 221, 223, 224, 225, 226, 227, 228];

        $educationCohortQuery = function (array $educationIds) {
            return $this->getPageTableQuery()
                ->whereHas('student.studentEducations', function ($q) use ($educationIds) {
                    $q->whereIn('education_id', $educationIds)
                        ->where(function ($query) {
                            $query->where(function ($subQ) {
                                $subQ->whereColumn('entry_time', '>=', 'start_date')
                                    ->whereColumn('entry_time', '<=', 'end_date');
                            })->orWhereNull('end_date');
                        });
                })
                ->count();
        };

        return [
            Stat::make(__('general.labels.total_entries'), $totals->total ?? 0)
                 ->description(__('general.labels.total_students_description', [
                     'count' => $totals->students ?? 0,
                 ])),

            Stat::make(__('general.labels.active_days_per_center'), (int) round($avgActiveDays))
                ->description($activeDaysDescription),

            Stat::make(__('general.entry-types.' . EntryType::Free()), (int) ($entryTypeCounts[EntryType::Free()] ?? 0))
                ->extraAttributes(['class' => 'donor-hide']),
            Stat::make(__('general.entry-types.' . EntryType::Daily()), (int) ($entryTypeCounts[EntryType::Daily()] ?? 0)),
            Stat::make(__('general.entry-types.' . EntryType::Monthly()), (int) ($entryTypeCounts[EntryType::Monthly()] ?? 0)),
            Stat::make(__('general.entry-types.' . EntryType::Course()), (int) ($entryTypeCounts[EntryType::Course()] ?? 0)),
            Stat::make(__('general.entry-types.' . EntryType::Hall()), (int) ($entryTypeCounts[EntryType::Hall()] ?? 0)),
            Stat::make(__('general.entry-types.' . EntryType::Event()), (int) ($entryTypeCounts[EntryType::Event()] ?? 0)),
            Stat::make(__('general.entry-types.' . EntryType::FGD()), (int) ($entryTypeCounts[EntryType::FGD()] ?? 0)),

            Stat::make(__('general.labels.architecture_student_entries'), $educationCohortQuery($architectureEducationIds)),

            Stat::make(__('general.labels.dentistry_student_entries'), $educationCohortQuery($dentistryEducationIds)),

            // Dynamically generated Stats for all reference columns
            ...$referenceStatArray,
        ];
    }
}

