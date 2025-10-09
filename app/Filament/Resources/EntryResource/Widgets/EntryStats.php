<?php

namespace App\Filament\Resources\EntryResource\Widgets;

use App\Enums\EntryType;
use App\Filament\Resources\EntryResource\Pages\ListEntries;
use Filament\Infolists\Components\Fieldset;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;


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

            Stat::make(__('general.labels.print-service'), $this->getPageTableQuery()->where('use_printer', true)->count()),
            Stat::make(__('general.labels.mecrator-service'), $this->getPageTableQuery()->where('use_mecrator', true)->count()),
            Stat::make(__('general.labels.drawer-service'), $this->getPageTableQuery()->where('use_drawer', true)->count()),

            Stat::make('طلاب العمارة', $this->getPageTableQuery()->whereHas('student.educations', fn($q) => $q->where('educations.id', 65)->distinct('student_id'))->count()),
            Stat::make('طلاب الاسنان', $this->getPageTableQuery()->whereHas('student.educations', fn($q) => $q->whereIn('educations.id', [77, 136])->distinct('student_id'))->count()),
        ];

    }
}
