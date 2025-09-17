<?php

namespace App\Exports;

use App\Models\Center;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EntriesByCenterExport implements FromCollection, WithHeadings
{
    protected array $filters;
    protected Collection $centers;
    protected bool $groupByDay;
    protected string $periodLabel;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function headings(): array
    {
        $this->prepare();

        $headings = [$this->periodLabel];
        foreach ($this->centers as $center) {
            $headings[] = $center->name;
        }
        return $headings;
    }

    public function collection(): Collection
    {
        $pivotedData = $this->getPivotedData();
        return new Collection(array_values($pivotedData));
    }

    protected function prepare(): void
    {
        $centerIds = $this->filters['center'] ?? null;
        $this->centers = $centerIds ? Center::whereIn('id', $centerIds)->get() : Center::all();

        $startDate = Carbon::parse($this->filters['startDate'] ?? now()->startOfYear());
        $endDate = Carbon::parse($this->filters['endDate'] ?? now());

        $diffInDays = $endDate->diffInDays($startDate);
        $this->groupByDay = $diffInDays <= 31;
        $this->periodLabel = $this->groupByDay ? 'Day' : 'Month';
    }

    protected function getPivotedData(): array
    {
        $startDate = Carbon::parse($this->filters['startDate'] ?? now()->startOfYear())->startOfDay();
        $endDate = Carbon::parse($this->filters['endDate'] ?? now())->endOfDay();

        $dateFormat = $this->groupByDay ? '%Y-%m-%d' : '%Y-%m';

        $entries = DB::table('student_entries')
            ->select(DB::raw("DATE_FORMAT(entry_time, '{$dateFormat}') as period"), 'center_id', DB::raw('COUNT(id) as count'))
            ->whereBetween('entry_time', [$startDate, $endDate])
            ->whereIn('center_id', $this->centers->pluck('id'))
            ->groupBy('period', 'center_id')
            ->orderBy('period')
            ->get();

        $pivotedData = [];
        $period = clone $startDate;
        while ($period <= $endDate) {
            $key = $this->groupByDay ? $period->format('Y-m-d') : $period->format('Y-m');
            if (!isset($pivotedData[$key])) {
                $pivotedData[$key] = [$this->periodLabel => $key];
                foreach ($this->centers as $center) {
                    $pivotedData[$key][$center->name] = 0;
                }
            }
            if ($this->groupByDay) {
                $period->addDay();
            } else {
                $period->addMonth()->startOfMonth();
            }
        }

        foreach ($entries as $entry) {
            $centerName = $this->centers->find($entry->center_id)?->name;
            if ($centerName && isset($pivotedData[$entry->period])) {
                $pivotedData[$entry->period][$centerName] = $entry->count;
            }
        }

        return $pivotedData;
    }
}
