<?php

namespace App\Filament\Widgets;

use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EntryGenderChart extends Widget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Gender Distribution (Entries)';
    protected static ?int $sort = 6;
    protected static string $view = 'exports.entry-gender-chart';

    public function data(): array
    {
        $query = StudentEntry::query()
            ->with('student')
            ->when($this->filters['startDate'] ?? null, fn($q) =>
                $q->whereDate('entry_time', '>=', Carbon::parse($this->filters['startDate']))
            )
            ->when($this->filters['endDate'] ?? null, fn($q) =>
                $q->whereDate('entry_time', '<=', Carbon::parse($this->filters['endDate']))
            )
            ->when($this->filters['center'] ?? null, fn($q) =>
                $q->whereIn('center_id', (array) $this->filters['center'])
            )
            ->when($this->filters['city'] ?? null, fn($q) =>
                $q->whereHas('center', fn($q) =>
                    $q->whereIn('city_id', (array) $this->filters['city'])
                )
            )
            ->get()
            ->groupBy(fn($entry) => $entry->student->gender ?? 0)
            ->map(fn($group) => $group->count());



        $query[3] = collect($query)->sum();
        $genderLabels = [
            0 => 'Unknown',
            3 => 'Total',
            1 => 'Male',
            2 => 'Female',

        ];

        $labels = [];
        $data = [];

        foreach ($genderLabels as $key => $label) {
            $labels[] = $label;
            $data[] = $query[$key] ?? 0;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Entries',
                    'data' => $data,
                    'backgroundColor' => [
                        'rgba(255, 159, 64, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(255, 205, 86, 0.2)',

                    ],
                    'borderColor' => [
                        'rgb(255, 159, 64)',
                        'rgb(54, 162, 235)',
                        'rgb(255, 99, 132)',
                        'rgb(255, 205, 86)',

                    ],
                    'borderWidth' => 1,
                ],
            ],
        ];
    }

    public function export(): BinaryFileResponse
    {
        $data = $this->data();
        $rows = [];

        foreach ($data['labels'] as $i => $label) {
            $rows[] = [
                'Gender' => $label,
                'Entries' => $data['datasets'][0]['data'][$i],
            ];
        }

        return Excel::download(new \App\Exports\ArrayExport($rows, [
            'Gender' => 'Gender',
            'Entries' => 'Entries',
        ], 'exports.gender-distribution-entries'), 'entry_gender_distribution.xlsx');
    }
}
