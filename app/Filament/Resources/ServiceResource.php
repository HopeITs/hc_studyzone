<?php

namespace App\Filament\Resources;

use App\Enums\Roles;
use App\Filament\Resources\ServiceResource\RelationManagers;
use App\Models\Center;
use App\Models\Service;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    public static function getNavigationLabel(): string
    {
        return __('general.services_module.services');
    }

    public static function getPluralLabel(): string
    {
        return __('general.services_module.services');
    }

    public static function getModelLabel(): string
    {
        return __('general.services_module.service');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();
        $query = parent::getEloquentQuery();

        if ($user->role === Roles::GeneralAdmin()) {
            return $query;
        }

        if ($user->role === Roles::CityAdmin() && $user->city_id) {
            return $query->whereHas('centerPrices', function ($q) use ($user) {
                $q->whereHas('center', function ($p0) use ($user) {
                    $p0->where('city_id', $user->city_id);
                });
            });
        } elseif ($user->role === Roles::CenterAdmin()) {
            return $query->whereHas('centerPrices', function ($q) use ($user) {
                $q->where('center_id', $user->center_id);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        $user = Auth::user();
        $isGeneralAdmin = $user->role === Roles::GeneralAdmin();

        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('general.labels.service_name'))
                            ->required()
                            ->maxLength(255)
                            ->disabled(!$isGeneralAdmin), // Disable for City/Center Admins

                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->label(__('general.services_module.category'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(!$isGeneralAdmin), // Disable for City/Center Admins
                                  Forms\Components\Select::make('reference_column_name')
                            ->options([
                                'use_internet' =>  'use_internet' ,
                                'use_mecrator' =>  'use_mecrator' ,
                                'use_drawer' =>  'use_drawer' ,
                                'use_plotter' =>  'use_plotter' ,
                                'use_printer' =>  'use_printer' ,
                                'reservation' =>  'reservation' ,
                                'stationary' =>  'stationary' ,
                            ])
                            ->label(__('general.services_module.reference'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(!$isGeneralAdmin),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('general.services_module.active'))
                            ->default(true)
                            ->visible($isGeneralAdmin),

                        Forms\Components\TextInput::make('initial_price')
                            ->label(__('general.services_module.initial_price').' (عملة جديدة) ')
                            ->numeric()
                            ->prefix('SYP')
                            ->required($isGeneralAdmin && ! $form->getRecord())
                            ->visible($isGeneralAdmin)
                            ->helperText('سيتم تعيين هذا السعر لجميع المراكز بشكل افتراضي - يرجى إدخال السعر بالعملة الجديدة'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        $isGeneralAdmin = $user->role === Roles::GeneralAdmin();
        $isCityAdmin = $user->role === Roles::CityAdmin();
        $isCenterAdmin = $user->role === Roles::CenterAdmin();
        $cityId = $user->city_id;

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('general.labels.service_name'))
                    ->sortable()
                    ->searchable(),
                        TextColumn::make('reference_column_name')
                    ->label(__('general.labels.reference'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('category.unit')
                    ->label(__('general.services_module.unit'))
                    ->formatStateUsing(fn ($state) => __('general.services_module.units.'.$state))
                    ->badge()
                    ->color('info'),

                TextColumn::make('price_display')
                    ->label(__('general.services_module.price'))
                    ->state(function (Service $record) use ($isCenterAdmin, $isCityAdmin, $cityId) {
                        if ($isCenterAdmin) {
                            return $record->price ? $record->price . ' SYP' : null;
                        }

                        if ($isCityAdmin && $cityId) {
                            $range = $record->getPriceRangeForCity($cityId);
                        } else {
                            $range = $record->centerPrices()
                                ->where('is_active', true)
                                ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
                                ->first();
                        }

                        if ($range && $range->min_price !== null) {
                            $min = $range->min_price;
                            $max = $range->max_price;
                            return $min === $max ? $min.' SYP' : $min.' - '.$max.' SYP';
                        }

                        return null;
                    })
                    ->color(fn ($state) => $state !== null ? 'success' : 'warning'),

                TextColumn::make('average_price')
                    ->label(__('general.services_module.average_price'))
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 2).' SYP' : '-')
                    ->color('info')
                    ->visible($isGeneralAdmin || $isCityAdmin),

                TextColumn::make('centers_count')
                    ->label(__('general.services_module.centers_count'))
                    ->state(function (Service $record) use ($isCityAdmin, $cityId) {
                        $query = $record->centerPrices()->where('is_active', true);
                        if ($isCityAdmin && $cityId) {
                            $query->whereHas('center', fn($q) => $q->where('city_id', $cityId));
                        }
                        return $query->count();
                    })
                    ->badge()
                    ->visible($isGeneralAdmin || $isCityAdmin),
            ])
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersFormColumns(4)
            ->filters([
                SelectFilter::make('category_id')
                    ->label(__('general.services_module.category'))
                    ->relationship('category', 'name')
                    ->searchable()
                    ->multiple()
                    ->preload()
                    ->columnSpan(1),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => $isGeneralAdmin || $isCityAdmin ), // Allow all to edit

                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => $isGeneralAdmin),


            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->visible(fn () => $isGeneralAdmin),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ServiceResource\Pages\ListServices::route('/'),
            'create' => ServiceResource\Pages\CreateService::route('/create'),
            'edit' => ServiceResource\Pages\EditService::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [RelationManagers\PricesRelationManager::class];
    }

    public static function canCreate(): bool
    {
        return Auth::user()->role === Roles::GeneralAdmin();
    }

    public static function canEdit($record): bool
    {
        $user = Auth::user();
        // Allow General Admin, City Admin, and Center Admin to access the edit page
        return $user->role === Roles::GeneralAdmin() || $user->role === Roles::CityAdmin() ;
    }

    public static function canDelete($record): bool
    {
        return Auth::user()->role === Roles::GeneralAdmin();
    }
}
