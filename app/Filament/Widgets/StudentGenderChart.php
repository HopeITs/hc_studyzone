<?php

namespace App\Filament\Widgets;

use App\Models\Student;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class StudentGenderChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = null;
    protected static ?int $sort = 5;
    protected string $color = 'primary';

    public function getHeading(): string
    {
        return __('general.labels.new_students_gender_distribution');
    }

    protected function getType(): string
    {
        return 'bar'; // or 'bar'
    }

    protected function getData(): array
    {
        $startDate = !is_null($this->pageFilters['startDate'] ?? null)
            ? Carbon::parse($this->pageFilters['startDate'])->startOfDay()
            : now()->startOfYear();

        $endDate = !is_null($this->pageFilters['endDate'] ?? null)
            ? Carbon::parse($this->pageFilters['endDate'])->endOfDay()
            : now()->endOfMonth();

        $cityId = $this->pageFilters['city'] ?? null;
        $centerId = $this->pageFilters['center'] ?? null;

        $query = Student::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($this->pageFilters['center'] ?? null, fn($q) => $q->whereHas('entries', fn($q) => $q->whereIn('center_id', (array)$this->pageFilters['center'])))
            ->when($this->pageFilters['city'] ?? null, fn($q) => $q->whereHas('entries.center', fn($q) => $q->whereIn('city_id', (array)$this->pageFilters['city'])))
            ->selectRaw('gender, COUNT(*) as total')
            ->groupBy('gender')
            ->pluck('total', 'gender')
            ->toArray();


        $query[3] = collect($query)->sum();
        $genderLabels = [
            3 => __('general.labels.total'),
            1 => __('general.labels.males'),
            2 => __('general.labels.females'),
            0 => __('general.labels.unknown'),

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
                    'label' => __('general.labels.new_students'),
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
}
