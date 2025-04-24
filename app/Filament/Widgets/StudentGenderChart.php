<?php

namespace App\Filament\Widgets;

use App\Models\Student;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class StudentGenderChart extends Widget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Gender Distribution (Students)';
    protected static ?int $sort = 5;
    protected static string $color = 'primary';
    protected static string $view = 'exports.student-gender-chart';

    protected function getType(): string
    {
        return 'bar'; // or 'bar'
    }

    protected function data(): array
    {
        $query = Student::query()
            ->when($this->filters['startDate'] ?? null, fn($q) => $q->whereDate('created_at', '>=', Carbon::parse($this->filters['startDate']))
            )
            ->when($this->filters['endDate'] ?? null, fn($q) => $q->whereDate('created_at', '<=', Carbon::parse($this->filters['endDate']))
            )
            ->when($this->filters['center'] ?? null, fn($q) => $q->whereHas('entries', fn($q) => $q->whereIn('center_id', (array)$this->filters['center'])))
            ->when($this->filters['city'] ?? null, fn($q) => $q->whereHas('entries.center', fn($q) => $q->whereIn('city_id', (array)$this->filters['city'])))
            ->selectRaw('gender, COUNT(*) as total')
            ->groupBy('gender')
            ->pluck('total', 'gender')
            ->toArray();


        $query[3] = collect($query)->sum();
        $genderLabels = [
            3 => 'Total',
            1 => 'Male',
            2 => 'Female',
            0 => 'Unknown',

        ];

        $labels = [];
        $data = [];

        foreach ($genderLabels as $key => $label) {
            $labels[] = $label;
            $data[] = $query[$key] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Students',
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
                ],
            ],
            'labels' => $labels,
        ];
    }

    public function export(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $data = $this->data();
        $rows = [];

        foreach ($data['labels'] as $i => $label) {
            $rows[] = [
                'Gender' => $label,
                'Students' => $data['datasets'][0]['data'][$i],
            ];
        }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ArrayExport($rows, [
                'Gender' => 'Gender',
                'Students' => 'Students',
            ], 'exports.student-gender-chart'),
            'student_gender_distribution.xlsx'
        );
    }
}
