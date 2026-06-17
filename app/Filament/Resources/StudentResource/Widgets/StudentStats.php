<?php

namespace App\Filament\Resources\StudentResource\Widgets;

use App\Enums\Gender;
use App\Models\Student;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StudentStats extends BaseWidget
{

    protected function getStats(): array
    {
        return [
            Stat::make(__('general.labels.total_students'), Student::count()),
            Stat::make(__('general.labels.male_students'), Student::where('gender', Gender::Male)->count()),
            Stat::make(__('general.labels.female_students'), Student::where('gender', Gender::Female)->count()),
        ];
    }
}
