<?php

namespace App\Exports;

use App\Models\Center;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Carbon\Carbon;

class EntriesByCenterDailyExport implements FromView
{
    protected $startDate;
    protected $endDate;

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
            ->with(['entries' => function ($q) use ($startDate, $endDate) {
                $q->selectRaw('center_id, DATE(entry_time) as ymd, COUNT(*) as total')
                    ->whereBetween('entry_time', [$startDate, $endDate])
                    ->groupBy('center_id', 'ymd');
            }])
            ->get(['id', 'name']);

        // Pivot rows
        $data = [];
        $current = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        while ($current <= $end) {
            $ymd = $current->format('Y-m-d');
            $row = ['day' => $current->format('d M Y')];

            foreach ($centers as $center) {
                $match = $center->entries->firstWhere('ymd', $ymd);
                $row[$center->name] = $match->total ?? 0;
            }

            $data[] = $row;
            $current->addDay();
        }

        return view('exports.entries_by_center_daily', [
            'data' => $data,
            'centers' => $centers->pluck('name'),
        ]);
    }
}
