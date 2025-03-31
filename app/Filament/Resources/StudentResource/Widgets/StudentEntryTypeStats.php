<?php

namespace App\Filament\Resources\StudentResource\Widgets;

use App\Models\StudentEntry;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StudentEntryTypeStats extends BaseWidget
{
    protected function getStats(): array
    {

            $stats = [];

        $entryTypes = StudentEntry::groupBy('entry_type')
            ->selectRaw('entry_type, count(*) as total')
            ->get();

        foreach ($entryTypes as $entryType) {
            $stats[] = Stat::make(__('general.entry-types.'.$entryType->entry_type), $entryType->total);
        }

        return $stats;

    }
}
