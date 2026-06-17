<?php

namespace App\Filament\Resources\UserResource\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserStats extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make(__('general.labels.total_users'), User::count()),
            Stat::make(__('general.labels.active_users'), User::where('is_active', true)->count()),
            Stat::make(__('general.roles.general_admin'), User::where('role', 'general_admin')->count()),
            Stat::make(__('general.roles.city_admin'), User::where('role', 'city_admin')->count()),
            Stat::make(__('general.roles.center_admin'), User::where('role', 'center_admin')->count()),
        ];
    }
}
