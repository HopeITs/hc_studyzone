<?php

namespace App\Filament\Resources\EntryResource\Widgets;

use App\Enums\EntryPurpose;
use App\Enums\EntryType;
use App\Filament\Resources\EntryResource\Pages\ListEntries;
use App\Models\EntryService;
use App\Models\Service;
use App\Models\StudentEntry;
use App\Support\FinanceDisplay;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Reactive;

class EntryStats extends BaseWidget
{
    use InteractsWithPageTable;

    #[Reactive]
    public array $hiddenCards = [];

    protected ?string $pollingInterval = null;

    protected function getTablePage(): string
    {
        return ListEntries::class;
    }

    protected function getColumns(): int
    {
        return 4;
    }

    public static function cardOptions(): array
    {
        $options = [
            'total_entries' => __('general.labels.total_entries'),
            'active_days' => __('general.labels.active_days_per_center'),
            'work' => __('general.labels.working_entries'),
            'study' => __('general.labels.studying_entries'),
            'free' => __('general.entry-types.'.EntryType::Free()),
            'daily' => __('general.entry-types.'.EntryType::Daily()),
            'monthly' => __('general.entry-types.'.EntryType::Monthly()),
            'course' => __('general.entry-types.'.EntryType::Course()),
            'hall' => __('general.entry-types.'.EntryType::Hall()),
            'event' => __('general.entry-types.'.EntryType::Event()),
            'fgd' => __('general.entry-types.'.EntryType::FGD()),
            'architecture' => __('general.labels.architecture_student_entries'),
            'dentistry' => __('general.labels.dentistry_student_entries'),
        ];

        foreach (self::referenceColumns() as $column) {
            $options['service:'.$column] = self::serviceLabel($column);
        }

        return $options;
    }

    private static function referenceColumns(): array
    {
        return Service::whereNotNull('reference_column_name')
            ->pluck('reference_column_name')
            ->unique()
            ->filter()
            ->all();
    }

    private static function serviceLabel(string $column): string
    {
        return match ($column) {
            'use_drawer' => __('general.labels.drawer'),
            'use_mecrator' => __('general.labels.mecrator'),
            'use_printer', 'use_pinter' => __('general.labels.printer'),
            'use_plotter' => __('general.labels.plotter'),
            'stationary' => __('general.labels.stationery'),
            'use_internet' => __('general.labels.internet'),
            'reservation' => __('general.labels.reservation_short'),
            default => ucfirst(str_replace(['use_', '_'], ['', ' '], $column)),
        };
    }

    protected function getStats(): array
    {
        // Get base query for entries to fetch IDs
        $entryIdsQuery = $this->getPageTableQuery()->select('id');

        // 1. Get all unique reference columns from services
        $referenceColumns = array_filter(
            self::referenceColumns(),
            fn (string $column): bool => ! in_array('service:'.$column, $this->hiddenCards, true),
        );

        // 2. Calculate entry counts for each reference column using a single efficient query
        $counts = null;
        if (! empty($referenceColumns)) {
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

            $referenceStatArray['service:'.$col] = Stat::make(self::serviceLabel($col), $entriesCount)
                ->description(new HtmlString(
                    '<span class="donor-hide">'.e(__('general.labels.quantity_total_description', [
                        'quantity' => $quantity,
                        'total' => number_format($amount),
                    ])).'</span>'
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

        $dailyCount = (int) ($entryTypeCounts[EntryType::Daily()] ?? 0);
        $daily = Stat::make(__('general.entry-types.'.EntryType::Daily()), $dailyCount);

        if (! Schema::hasColumn('student_entries', 'daily_admission_fee')) {
            $daily->description(new HtmlString('<span class="donor-hide">'.e(__('general.admission_preview.fees_pending')).'</span>'));
        } elseif (! in_array('daily', $this->hiddenCards, true)) {
            $fees = $this->getPageTableQuery()
                ->where('entry_type', EntryType::Daily())
                ->reorder()
                ->toBase()
                ->selectRaw('SUM(daily_admission_fee) as total, COUNT(daily_admission_fee) as priced')
                ->first();

            $description = $dailyCount > 0 && (int) $fees->priced === 0
                ? __('general.admission_preview.no_recorded_fees')
                : __('general.admission_preview.'.((int) $fees->priced < $dailyCount ? 'partial_total' : 'daily_total'), [
                    'amount' => FinanceDisplay::format((int) $fees->total),
                    'currency' => __('general.admission_preview.'.(FinanceDisplay::enabled() ? 'old' : 'new')),
                ]);

            $daily->description(new HtmlString('<span class="donor-hide">'.e($description).'</span>'));
        }

        $monthly = Stat::make(__('general.entry-types.'.EntryType::Monthly()), (int) ($entryTypeCounts[EntryType::Monthly()] ?? 0));

        if (Schema::hasColumn('student_entries', 'monthly_status') && ! in_array('monthly', $this->hiddenCards, true)) {
            $subscriptions = $this->getPageTableQuery()
                ->where('entry_type', EntryType::Monthly())
                ->reorder()
                ->toBase()
                ->selectRaw("SUM(monthly_status = 'charged') as charged, SUM(monthly_status = 'covered') as covered, SUM(monthly_admission_fee) as total")
                ->first();

            $monthly->description(new HtmlString('<span class="donor-hide">'.e(__('general.admission_categories.monthly_summary', [
                'charged' => (int) $subscriptions->charged,
                'covered' => (int) $subscriptions->covered,
                'amount' => FinanceDisplay::format((int) $subscriptions->total),
                'currency' => __('general.admission_preview.'.(FinanceDisplay::enabled() ? 'old' : 'new')),
            ])).'</span>'));
        }

        // Purpose breakdown (work / study) in one grouped query, respecting the table filters
        $purposeCounts = $this->getPageTableQuery()
            ->reorder()
            ->toBase()
            ->selectRaw('purpose, COUNT(*) as total')
            ->groupBy('purpose')
            ->pluck('total', 'purpose');

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

        $stats = [
            'total_entries' => Stat::make(__('general.labels.total_entries'), $totals->total ?? 0)
                ->description(__('general.labels.total_students_description', [
                    'count' => $totals->students ?? 0,
                ])),

            'active_days' => Stat::make(__('general.labels.active_days_per_center'), (int) round($avgActiveDays))
                ->description($activeDaysDescription),

            'work' => Stat::make(__('general.labels.working_entries'), (int) ($purposeCounts[EntryPurpose::Working()] ?? 0)),
            'study' => Stat::make(__('general.labels.studying_entries'), (int) ($purposeCounts[EntryPurpose::Studying()] ?? 0)),

            'free' => Stat::make(__('general.entry-types.'.EntryType::Free()), (int) ($entryTypeCounts[EntryType::Free()] ?? 0))
                ->extraAttributes(['class' => 'donor-hide']),
            'daily' => $daily,
            'monthly' => $monthly,
            'course' => Stat::make(__('general.entry-types.'.EntryType::Course()), (int) ($entryTypeCounts[EntryType::Course()] ?? 0)),
            'hall' => Stat::make(__('general.entry-types.'.EntryType::Hall()), (int) ($entryTypeCounts[EntryType::Hall()] ?? 0)),
            'event' => Stat::make(__('general.entry-types.'.EntryType::Event()), (int) ($entryTypeCounts[EntryType::Event()] ?? 0)),
            'fgd' => Stat::make(__('general.entry-types.'.EntryType::FGD()), (int) ($entryTypeCounts[EntryType::FGD()] ?? 0)),

            'architecture' => Stat::make(__('general.labels.architecture_student_entries'), $educationCohortQuery($architectureEducationIds)),

            'dentistry' => Stat::make(__('general.labels.dentistry_student_entries'), $educationCohortQuery($dentistryEducationIds)),

            // Dynamically generated Stats for all reference columns
            ...$referenceStatArray,
        ];

        return array_diff_key($stats, array_flip($this->hiddenCards));
    }
}
