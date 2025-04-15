<?php

namespace App\Filament\Widgets;

use App\Models\Student;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class NewStudentsChart extends Widget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'New Students';

    protected static ?int $sort = 2;

    protected static string $view = 'exports.new-students-chart';

    protected function getType(): string
    {
        return 'line';
    }

    protected function data(): array
    {
        $startDate = !is_null($this->filters['startDate'] ?? null)
            ? Carbon::parse($this->filters['startDate'])->startOfMonth()
            : now()->startOfYear();

        $endDate = !is_null($this->filters['endDate'] ?? null)
            ? Carbon::parse($this->filters['endDate'])->endOfMonth()
            : now()->endOfMonth();

        $cityId = $this->filters['city'] ?? null;
        $centerId = $this->filters['center'] ?? null;

        $query = Student::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_active', true)
            ->groupBy('month')
            ->orderBy('month');

        // Center filter via latest entry's center
        if ($centerId) {
            $query->whereHas('entries', fn ($q) => $q->where('center_id', $centerId));
        } elseif ($cityId) {
            $query->whereHas('entries.center', fn ($q) => $q->where('city_id', $cityId));
        }

        $results = $query->pluck('total', 'month')->toArray();

        // All months between range
        $months = [];
        $labels = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $key = $current->format('Y-m');
            $months[] = $key;
            $labels[] = $current->format('M Y');
            $current->addMonth();
        }

        $data = array_map(fn ($month) => $results[$month] ?? 0, $months);

        return [
            'datasets' => [
                [
                    'label' => 'New Students',
                    'data' => $data,
                    'fill' => 'start',
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
                'Month' => $label,
                'New Students' => $data['datasets'][0]['data'][$i],
            ];
        }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ArrayExport($rows, [
                'Month' => 'Month',
                'New Students' => 'New Students',
            ], 'exports.new-students-chart'),
            'new_students_per_month.xlsx'
        );
    }
}
