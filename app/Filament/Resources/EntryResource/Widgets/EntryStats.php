<?php

namespace App\Filament\Resources\EntryResource\Widgets;

use App\Enums\EntryType;
use App\Filament\Resources\EntryResource\Pages\ListEntries;
use App\Models\EntryService;
use App\Models\Service;
use App\Models\StudentEntry;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class EntryStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected static ?string $pollingInterval = null;

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

            // Map column names to readable Arabic labels
            $label = match($col) {
                'use_drawer' => 'المرسم',
                'use_mecrator' => 'الميكراتور',
                'use_pinter' => 'الطباعة',
                'use_plotter' => 'البلوتر',
                'stationary' => 'القرطاسية',
                'use_internet' => 'الانترنت',
                'reservation' => 'الحجز',
                default => ucfirst(str_replace(['use_', '_'], ['', ' '], $col)),
            };

            $referenceStatArray[] = Stat::make($label, $entriesCount)
                ->description("الكمية: {$quantity} - الإجمالي: " . number_format($amount) . " ل.س");
        }

        return [
            Stat::make('إجمالي الدخوليات', $this->getPageTableQuery()->count())
                 ->description('إجمالي الطلاب '.$this->getPageTableQuery()->distinct('student_id')->count('student_id') ),

            Stat::make(__('general.entry-types.' . EntryType::Free()), $this->getPageTableQuery()->where('entry_type', EntryType::Free())->count()),
            Stat::make(__('general.entry-types.' . EntryType::Daily()), $this->getPageTableQuery()->where('entry_type', EntryType::Daily())->count()),
            Stat::make(__('general.entry-types.' . EntryType::Monthly()), $this->getPageTableQuery()->where('entry_type', EntryType::Monthly())->count()),
            Stat::make(__('general.entry-types.' . EntryType::Course()), $this->getPageTableQuery()->where('entry_type', EntryType::Course())->count()),
            Stat::make(__('general.entry-types.' . EntryType::Hall()), $this->getPageTableQuery()->where('entry_type', EntryType::Hall())->count()),
            Stat::make(__('general.entry-types.' . EntryType::Event()), $this->getPageTableQuery()->where('entry_type', EntryType::Event())->count()),
            Stat::make(__('general.entry-types.' . EntryType::FGD()), $this->getPageTableQuery()->where('entry_type', EntryType::FGD())->count()),

            Stat::make(' دخوليات طلاب العمارة',
                $this->getPageTableQuery()
                    ->whereHas('student.studentEducations', function ($q) {
                        $q->whereIn('education_id', [65, 176, 242])
                            ->where(function ($query) {
                                $query->where(function ($subQ) {
                                    $subQ->whereColumn('entry_time', '>=', 'start_date')->
                                     whereColumn('entry_time', '<=', 'end_date');
                                })->orWhereNull('end_date');
                            });

                    })
                    ->count()
            ),

            Stat::make('دخوليات طلاب الاسنان',
                $this->getPageTableQuery()
                    ->whereHas('student.studentEducations', function ($q) {
                        $q->whereIn('education_id', [25, 77, 136, 221, 223, 224, 225, 226, 227, 228])
                            ->where(function ($query) {
                                $query->where(function ($subQ) {
                                    $subQ->whereColumn('entry_time', '>=', 'start_date')->
                                     whereColumn('entry_time', '<=', 'end_date');
                                })->orWhereNull('end_date');
                            });
                    })
                    ->count()
            ),

            // Dynamically generated Stats for all reference columns
            ...$referenceStatArray,
        ];
    }
}
