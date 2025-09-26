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
    protected static ?string $title = 'احصائيات';
    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Select::make('city')->options(City::pluck('name', 'id'))->multiple()->visible(auth()->user()->role === Roles::GeneralAdmin())->columnSpanFull(),
                        Select::make('center')
                            ->options(
                                auth()->user()->role === Roles::GeneralAdmin()
                                    ? Center::pluck('name', 'id')
                                    : Center::where('city_id', auth()->user()->city_id)->pluck('name', 'id')
                            )
                            ->multiple()
                            ->columnSpanFull(),
                        DatePicker::make('startDate')
                            ->default(now()->startOfYear())
                            ->maxDate(fn(Get $get) => $get('endDate') ?: now()),
                        DatePicker::make('endDate')
                            ->default(now())
                            ->maxDate(now()),
                    ])
                    ->columns(2),
            ]);
    }
}
