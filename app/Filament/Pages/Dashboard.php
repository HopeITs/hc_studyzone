<?php

namespace App\Filament\Pages;

use App\Enums\Roles;
use App\Models\Center;
use App\Models\City;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    use BaseDashboard\Concerns\HasFiltersForm;

    public function getTitle(): string
    {
        return __('general.labels.statistics');
    }

    public function filtersForm(Form $form): Form
    {
        $userRole = auth()->user()->role;
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Select::make('city')->label(__('general.labels.city'))->options(City::pluck('name', 'id'))->multiple()
                            ->visible($userRole === Roles::GeneralAdmin() || $userRole === Roles::Observer())->columnSpanFull(),
                        Select::make('center')
                            ->label(__('general.labels.center'))
                            ->options(
                                $userRole === Roles::GeneralAdmin() || $userRole === Roles::Observer()
                                    ? Center::orderBy('is_active','desc')->orderBy('order')->pluck('name', 'id')
                                    : Center::where('city_id', auth()->user()->city_id)->orderBy('order')->orderBy('is_active','desc')->pluck('name', 'id')
                            )
                            ->multiple()
                            ->columnSpanFull(),
                        DatePicker::make('startDate')
                            ->label(__('general.labels.start_date_filter'))
                            ->default(now()->startOfYear())
                            ->maxDate(fn(Get $get) => $get('endDate') ?: now()),
                        DatePicker::make('endDate')
                            ->label(__('general.labels.end_date_filter'))
                            ->default(now())
                            ->maxDate(now()),
                    ])
                    ->columns(2),
            ]);
    }
}
