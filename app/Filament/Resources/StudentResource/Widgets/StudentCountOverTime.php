<?php

namespace App\Filament\Resources\StudentResource\Widgets;

use App\Models\Student;
use Filament\Widgets\ChartWidget;

class StudentCountOverTime extends ChartWidget
{
    protected static ?string $heading = null;

    public function getHeading(): string
    {
        return __('general.labels.student_count_over_time');
    }

    protected function getData(): array
    {
        $data = [];

        // Daily
        $daily = Student::whereDate('created_at', today())->count();
        $data['daily'] = $daily;

        // Weekly
        $weekly = Student::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $data['weekly'] = $weekly;

        // Monthly
        $monthly = Student::whereMonth('created_at', now()->month)->count();
        $data['monthly'] = $monthly;

        // Yearly
        $yearly = Student::whereYear('created_at', now()->year)->count();
        $data['yearly'] = $yearly;

        return [
            'datasets' => [
                [
                    'label' => __('general.labels.student_count'),
                    'data' => array_values($data),
                ],
            ],
            'labels' => [
                __('general.labels.daily'),
                __('general.labels.weekly'),
                __('general.labels.monthly'),
                __('general.labels.yearly'),
            ],
        ];
    }


    protected function getType(): string
    {
        return 'line';
    }
}
