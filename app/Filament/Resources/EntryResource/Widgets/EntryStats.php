<?php

namespace App\Filament\Resources\EntryResource\Widgets;

use App\Enums\EntryType;
use App\Enums\Roles;
use App\Filament\Resources\EntryResource\Pages\ListEntries;
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


            Stat::make(__('general.labels.print-service'), $this->getPageTableQuery()->where('use_printer', true)->count()),
            Stat::make(__('general.labels.mecrator-service'), $this->getPageTableQuery()->where('use_mecrator', true)->count()),
            Stat::make(__('general.labels.drawer-service'), $this->getPageTableQuery()->where('use_drawer', true)->count()),
            Stat::make(__('general.labels.plotter-service'), $this->getPageTableQuery()->where('use_plotter', true)->count()),
                 Stat::make(__('general.labels.internet-service'), $this->getPageTableQuery()->where('use_internet', true)->count()),
        ];
    }
}
