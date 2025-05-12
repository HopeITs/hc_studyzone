<?php

namespace App\Filament\Resources\EntryResource\Widgets;

use App\Models\StudentEntry;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class EntryStats extends BaseWidget
{
    public array $tableFilters = [];
    protected function getStats(): array
    {
        $baseQuery = StudentEntry::query();
        $user = Auth::user();

        if ($user->hasRole('city_admin')) {
            $baseQuery->whereHas('center', fn($q) => $q->where('city_id', $user->city_id));
        } elseif ($user->hasRole('center_admin')) {
            $baseQuery->where('center_id', $user->center_id);
        } elseif (! $user->hasRole('general_admin')) {
            $baseQuery->whereRaw('1 = 0');
        }
        $filters = $this->tableFilters;

        dd($filters);



        if (!empty($filters['center.city.name'])) {
            $cities = Arr::wrap($filters['center.city.name']['values'] ?? []);
            $baseQuery->whereHas('center.city', fn($q) => $q->whereIn('id', $cities));
        }

        if (!empty($filters['center_id'])) {
            $baseQuery->whereIn('center_id', Arr::wrap($filters['center_id']['values'] ?? []));
        }

        if (!empty($filters['entry_time']['from'])) {
            $baseQuery->whereDate('entry_time', '>=', $filters['entry_time']['from']);
        }

        if (!empty($filters['entry_time']['to'])) {
            $baseQuery->whereDate('entry_time', '<=', $filters['entry_time']['to']);
        }

        // 🧮 Stats
        return [
            Card::make('إجمالي الدخوليات', $baseQuery->count()),

            Card::make('دخول نوع مجاني', $baseQuery->clone()->where('entry_type', 0)->count()),
            Card::make('دخول نوع يومي', $baseQuery->clone()->where('entry_type', 1)->count()),
            Card::make('دخول نوع شهري', $baseQuery->clone()->where('entry_type', 2)->count()),

            Card::make('استخدم طابعة', $baseQuery->clone()->where('use_printer', true)->count()),
            Card::make('استخدم المرسم', $baseQuery->clone()->where('use_drawer', true)->count()),
            Card::make('استخدم الميكراتور', $baseQuery->clone()->where('use_mecrator', true)->count()),

            Card::make('طلاب العمارة', $baseQuery->clone()
                ->whereHas('student.educations', fn($q) => $q->where('educations.id', 65))
                ->distinct('student_id')
                ->count('student_id')),

            Card::make('طلاب الأسنان', $baseQuery->clone()
                ->whereHas('student.educations', fn($q) => $q->whereIn('educations.id', [77, 136]))
                ->distinct('student_id')
                ->count('student_id')),
        ];
    }
}
