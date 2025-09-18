<?php

namespace App\Exports;

use App\Models\Center;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EntriesByCenterExport implements FromCollection, WithHeadings
{
    protected array $filters;
    protected Collection $centers;
    protected bool $groupByDay;
    protected string $periodLabel;
    protected array $headings;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
        $this->prepare();
    }

    public function headings(): array
    {
        return $this->headings;
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

        $this->headings = [$this->periodLabel];
        foreach ($this->centers as $center) {
            $this->headings[] = $center->name;
        }
    }

    protected function getPivotedData(): array
    {
        $startDate = Carbon::parse($this->filters['startDate'] ?? now()->startOfYear())->startOfDay();
        $endDate = Carbon::parse($this->filters['endDate'] ?? now())->endOfDay();

        $dbDateFormat = $this->groupByDay ? '%Y-%m-%d' : '%Y-%m';
        $phpDateFormat = $this->groupByDay ? 'Y-m-d' : 'Y-m';
        $centerIdNameMap = $this->centers->pluck('name', 'id');

        $pivotedData = [];
        $periodIterator = $startDate->clone();
        while ($periodIterator->lte($endDate)) {
            $periodKey = $periodIterator->format($phpDateFormat);
            if (!isset($pivotedData[$periodKey])) {
                $row = [$this->periodLabel => $periodKey];
                foreach ($this->centers as $center) {
                    $row[$center->name] = 0;
                }
                $pivotedData[$periodKey] = $row;
            }
            if ($this->groupByDay) {
                $periodIterator->addDay();
            } else {
                $periodIterator->addMonthNoOverflow()->startOfMonth();
            }
        }

        $entries = DB::table('student_entries')
            ->select(DB::raw("DATE_FORMAT(entry_time, '{$dbDateFormat}') as period"), 'center_id', DB::raw('COUNT(id) as count'))
            ->whereBetween('entry_time', [$startDate, $endDate])
            ->whereIn('center_id', $this->centers->pluck('id'))
            ->groupBy('period', 'center_id')
            ->get();

        foreach ($entries as $entry) {
            $centerName = $centerIdNameMap[$entry->center_id] ?? null;
            if ($centerName && isset($pivotedData[$entry->period])) {
                $pivotedData[$entry->period][$centerName] = $entry->count;
            }
        }

        return $pivotedData;
    }
}
