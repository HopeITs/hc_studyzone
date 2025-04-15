<?php

namespace App\Filament\Widgets;

use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class EntryToolsChart extends Widget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Entry Tool Usage Overview';
    protected static ?int $sort = 5;
    protected static string $view = 'exports.entry-tools-chart';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function data(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $centerIds = $this->filters['center'] ?? null;
        $cityIds = $this->filters['city'] ?? null;

        $query = StudentEntry::query()
            ->when($startDate, fn($q) => $q->whereDate('entry_time', '>=', Carbon::parse($startDate)))
            ->when($endDate, fn($q) => $q->whereDate('entry_time', '<=', Carbon::parse($endDate)))
            ->when($centerIds, fn($q) => $q->whereIn('center_id', (array)$centerIds))
            ->when($cityIds, fn($q) => $q->whereHas('center', fn($q) => $q->whereIn('city_id', (array)$cityIds))
            );

        $total = (clone $query)->count();
        $mecrator = (clone $query)->where('use_mecrator', true)->count();
        $printer = (clone $query)->where('use_printer', true)->count();
        $drawer = (clone $query)->where('use_drawer', true)->count();

        return [
            'datasets' => [
                [
                    'label' => 'Count',
                    'data' => [$total, $mecrator, $printer, $drawer],

                    'backgroundColor' => [
                        'rgba(255, 159, 64, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 99, 132, 0.2)',

                        'rgba(255, 205, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',

                        'rgba(153, 102, 255, 0.2)',
                        'rgba(201, 203, 207, 0.2)'
                    ],
                    'borderColor' => [
                        'rgb(255, 159, 64)',
                        'rgb(54, 162, 235)',
                        'rgb(255, 99, 132)',
                        'rgb(255, 205, 86)',
                        'rgb(75, 192, 192)',
                        'rgb(153, 102, 255)',
                        'rgb(201, 203, 207)'
                    ],
                    'borderWidth' => 1,
                ],
            ],
            'labels' => ['Total Entries', 'Used Mecrator', 'Used Printer', 'Used Drawer'],
        ];
    }

    public function export(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $data = $this->data();
        $rows = [];

        foreach ($data['labels'] as $i => $label) {
            $rows[] = [
                'Tool' => $label,
                'Count' => $data['datasets'][0]['data'][$i],
            ];
        }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ArrayExport($rows, [
                'Tool' => 'Tool',
                'Count' => 'Count',
            ], 'exports.entry-tools-chart'),
            'entry_tool_usage.xlsx'
        );
    }
}
