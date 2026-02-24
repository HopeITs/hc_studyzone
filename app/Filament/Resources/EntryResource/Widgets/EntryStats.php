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
            Stat::make('إجمالي الدخوليات', $this->getPageTableQuery()->count()),

            Stat::make(__('general.entry-types.' . EntryType::Free()), $this->getPageTableQuery()->where('entry_type', EntryType::Free())->count()),
            Stat::make(__('general.entry-types.' . EntryType::Daily()), $this->getPageTableQuery()->where('entry_type', EntryType::Daily())->count()),
            Stat::make(__('general.entry-types.' . EntryType::Monthly()), $this->getPageTableQuery()->where('entry_type', EntryType::Monthly())->count()),
            Stat::make(__('general.entry-types.' . EntryType::Course()), $this->getPageTableQuery()->where('entry_type', EntryType::Course())->count()),
            Stat::make(__('general.entry-types.' . EntryType::Hall()), $this->getPageTableQuery()->where('entry_type', EntryType::Hall())->count()),

           Stat::make('طلاب العمارة', 
    $this->getPageTableQuery()
        ->whereHas('student.studentEducations', function($q) {
            $q->where('education_id', 65)
              ->whereRaw('? BETWEEN start_date AND COALESCE(end_date, ?)', [
                  \DB::raw('entry_time'), 
                  \DB::raw('entry_time')
              ]);
        })
        ->count()
),
            Stat::make('طلاب الاسنان', 
    $this->getPageTableQuery()
        ->whereHas('student.studentEducations', function($q) {
            $q->whereIn('education_id', [77, 136])
              ->whereRaw('? BETWEEN start_date AND COALESCE(end_date, ?)', [
                  \DB::raw('entry_time'), 
                  \DB::raw('entry_time')
              ]);
        })
        ->count()
),


            Stat::make(__('general.labels.print-service'), $this->getPageTableQuery()->where('use_printer', true)->count()),
            Stat::make(__('general.labels.mecrator-service'), $this->getPageTableQuery()->where('use_mecrator', true)->count()),
            Stat::make(__('general.labels.drawer-service'), $this->getPageTableQuery()->where('use_drawer', true)->count()),
            Stat::make(__('general.labels.plotter-service'), $this->getPageTableQuery()->where('use_plotter', true)->count()),
        ];
    }
}
