<?php

namespace App\Filament\Resources;

use App\Models\ServicePrice;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\Filter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ServicePriceResource extends Resource
{
    protected static ?string $model = ServicePrice::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    public static function getNavigationLabel(): string 
    { 
        return __('general.services_module.service_prices'); 
    }

    public static function getPluralLabel(): string 
    { 
        return __('general.services_module.service_prices'); 
    }

    public static function getModelLabel(): string 
    { 
        return __('general.services_module.service_price'); 
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Select::make('service_id')
                            ->label(__('general.services_module.service_name'))
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
                                        $fail(__('general.services_module.validation_open_price'));
                                    }
                                },
                            ]),

                        Forms\Components\TextInput::make('price')
                            ->label(__('general.services_module.price'))
                            ->numeric()
                            ->prefix('SYP')
                            ->required(),

                        Forms\Components\DatePicker::make('start_at')
                            ->label(__('general.services_module.start_at'))
                            ->default(now())
                            ->required(),

                        Forms\Components\DatePicker::make('end_at')
                            ->label(__('general.services_module.end_at'))
                            ->disabled(fn (Forms\Get $get) => $get('is_default'))
                            ->dehydrated()
                            ->after('start_at'),

                        Forms\Components\Toggle::make('is_default')
                            ->label(__('general.services_module.active_price'))
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
                TextColumn::make('service.name')
                    ->label(__('general.services_module.service_name'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('price')
                    ->label(__('general.services_module.price'))
                    ->suffix(' SYP')
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label(__('general.services_module.default'))
                    ->boolean(),
                TextColumn::make('start_at')
                    ->label(__('general.services_module.start_at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('end_at')
                    ->label(__('general.services_module.end_at'))
                    ->date()
                    ->sortable()
                    ->placeholder(__('general.services_module.active')),
            ])
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersFormColumns(4)
            ->filters([
                Filter::make('start_at')
                    ->label(__('general.services_module.start_at'))
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('من')
                            ->columnSpan(1),
                        \Filament\Forms\Components\DatePicker::make('to')
                            ->label('إلى')
                            ->columnSpan(1),
                    ])
                    ->columnSpan(2)
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->where('start_at', '>=', $date))
                            ->when($data['to'], fn ($q, $date) => $q->where('start_at', '<=', $date));
                    }),
                SelectFilter::make('service_id')
                    ->label(__('general.services_module.service_name'))
                    ->relationship('service', 'name')
                    ->searchable()
                    ->multiple()
                    ->preload()
                    ->columnSpan(1),
                TernaryFilter::make('is_default')
                    ->label(__('general.services_module.default'))
                    ->placeholder('الكل')
                    ->trueLabel('نعم')
                    ->falseLabel('لا')
                    ->queries(
                        true: fn (Builder $query) => $query->where('is_default', true),
                        false: fn (Builder $query) => $query->where('is_default', false),
                        blank: fn (Builder $query) => $query,
                    )
                    ->columnSpan(1),
                TernaryFilter::make('active')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('نشط')
                    ->falseLabel('منتهي')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('end_at'),
                        false: fn (Builder $query) => $query->whereNotNull('end_at'),
                        blank: fn (Builder $query) => $query,
                    )
                    ->columnSpan(1),
            ])
            ->filtersTriggerAction(fn (Action $action) => $action->button()->label(''))
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->paginated([20, 50, 100, 200]);
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