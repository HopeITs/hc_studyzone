<?php

namespace App\Filament\Resources;

use App\Models\ServicePrice;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServicePriceResource extends Resource
{
    protected static ?string $model = ServicePrice::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Service Prices';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Select::make('service_id')
                            ->label(__('general.labels.service_name'))
                            ->options(Service::all()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->rules([
                                fn (Forms\Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $query = ServicePrice::where('service_id', $value)
                                        ->whereNull('end_at');

                                    if ($get('../../record')) {
                                        $query->where('id', '!=', $get('../../record')->id);
                                    }

                                    if ($query->exists()) {
                                        $fail('Cannot add a new price because the previous price for this service has no end date.');
                                    }
                                },
                            ]),

                        Forms\Components\TextInput::make('price')
                            ->numeric()
                            ->prefix('SYP')
                            ->required(),

                        Forms\Components\DatePicker::make('start_at')
                            ->default(now())
                            ->required(),

                        Forms\Components\DatePicker::make('end_at')
                            ->disabled(fn (Forms\Get $get) => $get('is_default'))
                            ->dehydrated()
                            ->after('start_at'),

                        Forms\Components\Toggle::make('is_default')
                            ->label('Active Price')
                            ->default(true)
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $set('end_at', null);
                                }
                            }),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('service.name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('price')->suffix(' SYP')->sortable(),
                Tables\Columns\IconColumn::make('is_default')->boolean()->label('Default'),
                Tables\Columns\TextColumn::make('start_at')->date()->sortable(),
                Tables\Columns\TextColumn::make('end_at')->date()->sortable()->placeholder('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ServicePriceResource\Pages\ListServicePrices::route('/'),
            'create' => ServicePriceResource\Pages\CreateServicePrice::route('/create'),
            'edit' => ServicePriceResource\Pages\EditServicePrice::route('/{record}/edit'),
        ];
    }
}