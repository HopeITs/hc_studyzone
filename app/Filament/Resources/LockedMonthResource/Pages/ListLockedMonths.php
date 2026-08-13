<?php

namespace App\Filament\Resources\LockedMonthResource\Pages;

use App\Filament\Resources\LockedMonthResource;
use App\Models\LockedMonth;
use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ListLockedMonths extends Page
{
    protected static string $resource = LockedMonthResource::class;

    protected static string $view = 'filament.resources.locked-month-resource.pages.list-locked-months';

    public function getViewData(): array
    {
        $years = $this->entryYears();

        $lockedPeriods = LockedMonth::query()
            ->whereIn('year', $years)
            ->where('is_locked', true)
            ->get(['year', 'month'])
            ->mapWithKeys(fn (LockedMonth $period) => ["{$period->year}-{$period->month}" => true])
            ->all();

        $entryCounts = $this->entryCounts($years);

        $monthsByYear = collect($years)
            ->mapWithKeys(fn (int $year) => [$year => $this->monthsForYear($year)])
            ->all();

        $fullyLockedYears = collect($years)
            ->mapWithKeys(fn (int $year) => [
                $year => collect(array_keys($monthsByYear[$year]))
                    ->every(fn (int $month) => isset($lockedPeriods["{$year}-{$month}"])),
            ])
            ->all();

        return compact('years', 'monthsByYear', 'lockedPeriods', 'fullyLockedYears', 'entryCounts');
    }

    public function setYearLock(int $year, bool $isLocked): void
    {
        abort_unless(in_array($year, $this->entryYears(), true), 404);

        DB::transaction(function () use ($year, $isLocked): void {
            foreach (array_keys($this->monthsForYear($year)) as $month) {
                $period = LockedMonth::firstOrNew([
                    'year' => $year,
                    'month' => $month,
                ]);

                $period->is_locked = $isLocked;

                if ($isLocked) {
                    $period->locked_by = Auth::id();
                    $period->locked_at = now();
                } else {
                    $period->unlocked_by = Auth::id();
                    $period->unlocked_at = now();
                }

                $period->save();
            }
        });

        Notification::make()
            ->title(__($isLocked
                ? 'general.labels.year_locked_successfully'
                : 'general.labels.year_unlocked_successfully'))
            ->success()
            ->send();
    }

    public function setMonthLock(int $year, int $month, bool $isLocked): void
    {
        abort_unless(
            isset($this->monthsForYear($year)[$month]) && in_array($year, $this->entryYears(), true),
            404
        );

        $period = LockedMonth::firstOrNew([
            'year' => $year,
            'month' => $month,
        ]);

        $period->is_locked = $isLocked;

        if ($isLocked) {
            $period->locked_by = Auth::id();
            $period->locked_at = now();
        } else {
            $period->unlocked_by = Auth::id();
            $period->unlocked_at = now();
        }

        $period->save();

        Notification::make()
            ->title(__($isLocked
                ? 'general.labels.month_locked_successfully'
                : 'general.labels.month_unlocked_successfully'))
            ->success()
            ->send();
    }

    /** @return array<int, int> */
    private function entryYears(): array
    {
        [$yearExpression] = $this->datePartExpressions();

        return StudentEntry::query()
            ->whereNotNull('entry_time')
            ->selectRaw("{$yearExpression} as entry_year")
            ->distinct()
            ->orderByDesc('entry_year')
            ->pluck('entry_year')
            ->map(fn ($year) => (int) $year)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $years
     * @return array<string, int>
     */
    private function entryCounts(array $years): array
    {
        if ($years === []) {
            return [];
        }

        [$yearExpression, $monthExpression] = $this->datePartExpressions();

        return StudentEntry::query()
            ->whereNotNull('entry_time')
            ->selectRaw("{$yearExpression} as entry_year, {$monthExpression} as entry_month, COUNT(*) as entries_count")
            ->groupByRaw("{$yearExpression}, {$monthExpression}")
            ->get()
            ->mapWithKeys(fn ($row) => [
                ((int) $row->entry_year).'-'.((int) $row->entry_month) => (int) $row->entries_count,
            ])
            ->all();
    }

    /** @return array{0: string, 1: string} */
    private function datePartExpressions(): array
    {
        return match (StudentEntry::query()->getConnection()->getDriverName()) {
            'sqlite' => [
                "CAST(strftime('%Y', entry_time) AS INTEGER)",
                "CAST(strftime('%m', entry_time) AS INTEGER)",
            ],
            'pgsql' => [
                'EXTRACT(YEAR FROM entry_time)',
                'EXTRACT(MONTH FROM entry_time)',
            ],
            default => ['YEAR(entry_time)', 'MONTH(entry_time)'],
        };
    }

    /** @return array<int, string> */
    private function monthsForYear(int $year): array
    {
        $lastMonth = $year === now()->year ? now()->month : 12;

        return collect(range(1, $lastMonth))
            ->mapWithKeys(fn (int $month) => [
                $month => Carbon::create(2000, $month, 1)->translatedFormat('F'),
            ])
            ->all();
    }
}
