<?php

namespace App\Filament\Resources\EntryResource\Widgets;

use App\Enums\EntryType;
use App\Filament\Resources\EntryResource\Pages\ListEntries;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;

class EntryTypeChart extends ChartWidget
{
    use InteractsWithPageTable;

    protected static ?string $heading = 'توزيع أنواع الدخوليات';
    protected static ?string $maxHeight = '280px';

    protected function getTablePage(): string
    {
        return ListEntries::class;
    }

    protected function getData(): array
    {
        $query = $this->getPageTableQuery();

        $types = [
            EntryType::Free(),
            EntryType::Daily(),
            EntryType::Monthly(),
            EntryType::Course(),
            EntryType::Hall(),
            EntryType::Event(),
            EntryType::FGT(),
        ];

        $labels = [];
        $data = [];

        // Colors for the chart segments
        $colors = [
            'rgba(54, 162, 235, 0.7)',   // Blue
            'rgba(255, 99, 132, 0.7)',   // Red
            'rgba(255, 206, 86, 0.7)',   // Yellow
            'rgba(75, 192, 192, 0.7)',   // Green
            'rgba(153, 102, 255, 0.7)',  // Purple
            'rgba(255, 159, 64, 0.7)',   // Orange
            'rgba(201, 203, 207, 0.7)',  // Grey
        ];

        foreach ($types as $type) {
            $labels[] = __('general.entry-types.' . $type);
            $data[] = (clone $query)->where('entry_type', $type)->count();
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderColor' => '#fff',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar'; // You can change this to 'pie' or 'bar' if you prefer
    }
}
