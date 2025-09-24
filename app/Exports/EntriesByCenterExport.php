<?php

namespace App\Exports;

use App\Models\StudentEntry;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Carbon\Carbon;

class EntriesByCenterExport implements FromView
{
    protected Carbon $startDate;
    protected Carbon $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = Carbon::parse($startDate)->startOfMonth();
        $this->endDate   = Carbon::parse($endDate)->endOfMonth();
    }

    public function view(): View
    {
        // Base query grouped by month and center
        $results = StudentEntry::query()
            ->selectRaw('YEAR(entry_time) as year, MONTH(entry_time) as month, center_id, COUNT(*) as total')
            ->whereBetween('entry_time', [$this->startDate, $this->endDate])
            ->groupBy('year', 'month', 'center_id')
            ->with('center:id,name')
            ->get();

        // Pivot data [month => [center => total]]
        $data = [];
        $centers = $results->pluck('center.name', 'center_id')->unique();

        $current = $this->startDate->copy();
        while ($current <= $this->endDate) {
            $monthKey = $current->format('Y-m');
            foreach ($centers as $id => $name) {
                $match = $results->first(fn($r) =>
                    $r->year == $current->year &&
                    $r->month == $current->month &&
                    $r->center_id == $id
                );
                $data[$monthKey][$name] = $match?->total ?? 0;
            }
            $current->addMonth();
        }

        return view('exports.entries_by_center', [
            'data' => $data,
            'centers' => $centers->values(),
        ]);
    }
}
