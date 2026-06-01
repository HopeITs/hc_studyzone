<?php

namespace App\Filament\Resources\EntryResource\Widgets;

use App\Enums\EntryType;
use App\Enums\Roles;
use App\Filament\Resources\EntryResource\Pages\ListEntries;
use App\Models\EntryService;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
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

        // 1. Calculate Printer Stats (aggregated from category unit = 'paper')
        $printerStats = EntryService::whereIn('entry_id', $entryIdsQuery)
            ->whereHas('service.category', function ($query) {
                $query->where('unit', 'paper');
            })
            ->selectRaw('SUM(amount) as total_count, SUM(total) as total_amount, COUNT(DISTINCT entry_id) as entries_count')
            ->first();

        $printerCount = $printerStats ? (int) $printerStats->total_count : 0;
        $printerTotal = $printerStats ? (int) $printerStats->total_amount : 0;
        $printerEntries = $printerStats ? (int) $printerStats->entries_count : 0;

        // 2. Calculate Other Services Stats (everything EXCEPT category unit = 'paper')
        $otherServiceStats = EntryService::whereIn('entry_id', $entryIdsQuery)
            ->whereDoesntHave('service.category', function ($query) {
                $query->where('unit', 'paper');
            })
            ->with('service.category') // Eager load service name for the label
            ->selectRaw('service_id, SUM(amount) as total_count, SUM(total) as total_amount, COUNT(DISTINCT entry_id) as entries_count')
            ->groupBy('service_id')
            ->get();

        // Build the stats array dynamically
        $otherServicesStatArray = [];
        foreach ($otherServiceStats as $stat) {
            $serviceName = $stat->service?->name ?? 'خدمة محذوفة';
            $count =  $stat->total_count . ' ' . __('general.services_module.units.'.$stat->service->category->unit);
            $total = (int) $stat->total_amount;
            $entries = (int) $stat->entries_count;

            $otherServicesStatArray[] = Stat::make($serviceName, $entries)
                  ->description( $count . ' ('.$total .' SYP)');
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
            Stat::make(__('general.entry-types.' . EntryType::FGT()), $this->getPageTableQuery()->where('entry_type', EntryType::FGT())->count()),

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

            // Printer Stat (Category unit = paper)
            Stat::make(__('general.labels.print-service'), $printerEntries )
                ->description( $printerCount . ' ' . __('general.services_module.units.paper') . ' ('.$printerTotal .' SYP)'),

            // Dynamically generated Stats for all other services
            ...$otherServicesStatArray,
        ];
    }
}
