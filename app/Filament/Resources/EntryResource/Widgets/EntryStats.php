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
        $query = $this->getPageTableQuery();

        $dateRange = $this->getDateRangeFromQuery($query);
        
        $totalEntries = $query->count();
        
        $educationStats = $this->getEducationStats($query, $dateRange);
        
        $stats = [
            Stat::make('إجمالي الدخوليات', $totalEntries),
            
            Stat::make(__('general.entry-types.' . EntryType::Free()), 
                $this->getPageTableQuery()->where('entry_type', EntryType::Free())->count()),
            Stat::make(__('general.entry-types.' . EntryType::Daily()), 
                $this->getPageTableQuery()->where('entry_type', EntryType::Daily())->count()),
            Stat::make(__('general.entry-types.' . EntryType::Monthly()), 
                $this->getPageTableQuery()->where('entry_type', EntryType::Monthly())->count()),
            Stat::make(__('general.entry-types.' . EntryType::Course()), 
                $this->getPageTableQuery()->where('entry_type', EntryType::Course())->count()),
            Stat::make(__('general.entry-types.' . EntryType::Hall()), 
                $this->getPageTableQuery()->where('entry_type', EntryType::Hall())->count()),
        ];
        
        foreach ($educationStats as $eduName => $count) {
            $stats[] = Stat::make($eduName, $count)
                ->description('عدد الدخوليات حسب الدراسة وقت الدخول')
                ->color('success');
        }
        
        $stats[] = Stat::make(__('general.labels.print-service'), 
            $this->getPageTableQuery()->where('use_printer', true)->count());
        $stats[] = Stat::make(__('general.labels.mecrator-service'), 
            $this->getPageTableQuery()->where('use_mecrator', true)->count());
        $stats[] = Stat::make(__('general.labels.drawer-service'), 
            $this->getPageTableQuery()->where('use_drawer', true)->count());
        $stats[] = Stat::make(__('general.labels.plotter-service'), 
            $this->getPageTableQuery()->where('use_plotter', true)->count());

        return $stats;
    }
    
    protected function getDateRangeFromQuery($query): array
    {
        $wheres = $query->getQuery()->wheres ?? [];
        
        $from = null;
        $to = null;
        
        foreach ($wheres as $where) {
            if (($where['column'] ?? '') === 'entry_time' && ($where['operator'] ?? '') === '>=') {
                $from = $where['value'] ?? null;
            }
            if (($where['column'] ?? '') === 'entry_time' && ($where['operator'] ?? '') === '<=') {
                $to = $where['value'] ?? null;
            }
        }
        
        return [
            'from' => $from,
            'to' => $to,
        ];
    }
    
    protected function getEducationStats($query, array $dateRange): array
    {
        $entries = clone $query;
        
        $entries = $entries->with(['student.studentEducations' => function($q) {
            $q->with('education')->orderBy('start_date');
        }])->get();
        
        $educationCounts = [];
        
        foreach ($entries as $entry) {
            $entryDate = $entry->entry_time->toDateString();
            
            $activeEdu = $entry->student->studentEducations
                ->filter(function($studentEdu) use ($entryDate) {
                    return $studentEdu->start_date <= $entryDate &&
                        ($studentEdu->end_date === null || $studentEdu->end_date >= $entryDate);
                })
                ->first();
            
            if ($activeEdu && $activeEdu->education) {
                $eduName = $activeEdu->education->getTranslation('name', 'ar');
                if (!isset($educationCounts[$eduName])) {
                    $educationCounts[$eduName] = 0;
                }
                $educationCounts[$eduName]++;
            }
        }

        arsort($educationCounts);
        return array_slice($educationCounts, 0, 5, true);
    }
}