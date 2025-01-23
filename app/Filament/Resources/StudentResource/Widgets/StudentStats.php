<?php

namespace App\Filament\Resources\StudentResource\Widgets;

use App\Models\Student;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StudentStats extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Students', Student::count()),
            Stat::make('Male Students', Student::where('gender', 'male')->count()),
            Stat::make('Female Students', Student::where('gender', 'female')->count()),
        ];
    }
}
