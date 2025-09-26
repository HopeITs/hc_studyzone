<?php

namespace App\Exports;

use App\Models\Center;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Carbon\Carbon;

use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EntriesByCenterExport implements FromView
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
            ->with(['entries' => function ($q) use ($startDate, $endDate) {
                $q->selectRaw('center_id, DATE_FORMAT(entry_time, "%Y-%m") as ym, COUNT(*) as total')
                    ->whereBetween('entry_time', [$startDate, $endDate])
                    ->groupBy('center_id', 'ym');
            }])
            ->get(['id', 'name']);

// Pivot rows
        $data = [];
        $current = Carbon::parse($startDate)->startOfMonth();
        while ($current <= $endDate) {
            $ym = $current->format('Y-m');
            $row = ['month' => $current->format('M Y')];

            foreach ($centers as $center) {
                $match = $center->entries->firstWhere('ym', $ym);
                $row[$center->name] = $match->total ?? 0;
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
