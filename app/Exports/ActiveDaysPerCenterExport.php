<?php

namespace App\Exports;

use App\Models\Center;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Carbon\Carbon;

use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ActiveDaysPerCenterExport implements FromView
{
    protected  $startDate;
    protected  $endDate;

    protected array $filters;
    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate   = $endDate;
    }

    public function view(): View
    {

        $startDate = $this->startDate;
        $endDate   = $this->endDate;

        $centers = Center::where('is_active', true)
            ->with([
                'entries' => function ($q) use ($startDate, $endDate) {
                    $q->selectRaw('center_id, YEAR(entry_time) as year, MONTH(entry_time) as month, COUNT(DISTINCT DATE(entry_time)) as active_days_count')
                        ->whereBetween('entry_time', [$startDate, $endDate])
                        ->groupBy('center_id', 'year', 'month');
                }
            ])
            ->get(['id', 'name']);

// Pivot rows
        $data = [];
        $current = Carbon::parse($startDate)->startOfMonth();
        while ($current <= $endDate) {
            $monthKey = $current->format('M Y');
            $row = ['month' => $monthKey];

            foreach ($centers as $center) {
                // Find the matching month record for this center
                $match = $center->entries->first(fn($r) =>
                    $r->year == $current->year &&
                    $r->month == $current->month
                );


                // Access 'active_days_count' (the alias we defined in the query)
                $row[$center->name] = $match ? (int)$match->active_days_count : 0;
            }

            $data[] = $row;
            $current->addMonth();
        }
        return view('exports.entries_by_center', [
            'data' => $data,
            'centers' => $centers->pluck('name'),
        ]);
    }

}
