<?php

namespace App\Filament\Resources\StudentResource\Widgets;

use App\Models\Education;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StudentEducationStats extends BaseWidget
{
    protected function getStats(): array
    {
        $stats = [];

        $educations = Education::withCount('students')->get();

        foreach ($educations as $education) {
            $stats[] = Stat::make($education->name, $education->students_count);
        }

        return $stats;

    }
}
