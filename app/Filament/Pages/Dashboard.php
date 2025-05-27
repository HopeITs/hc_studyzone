<?php

namespace App\Filament\Pages;

use App\Enums\Roles;
use App\Models\Center;
use App\Models\City;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    use BaseDashboard\Concerns\HasFiltersForm;

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Select::make('city')->options(City::pluck('name', 'id'))->multiple()->visible(auth()->user()->role === Roles::GeneralAdmin())->columnSpanFull(),
                        Select::make('center')->options(Center::pluck('name', 'id'))->multiple()->columnSpanFull()->visible(auth()->user()->role === Roles::GeneralAdmin()),
                        Select::make('center')->options(Center::where('city_id' , auth()->user()->city_id)->pluck('name', 'id'))->multiple()->columnSpanFull()->hidden(auth()->user()->role === Roles::GeneralAdmin()),

                        DatePicker::make('startDate')
                            ->maxDate(fn (Get $get) => $get('endDate') ?: now()),
                        DatePicker::make('endDate')
                            ->minDate(fn (Get $get) => $get('startDate') ?: now())
                            ->maxDate(now()),
                    ])
                    ->columns(2),
            ]);
    }
}
