<?php

namespace App\Filament\Resources;

use App\Enums\Roles;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\City;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Repeater;
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
            return $query->whereHas('cityPrices', function ($q) use ($user) {
                $q->where('city_id', $user->city_id);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public static function form(Form $form): Form
    {
        $user = Auth::user();
        $isGeneralAdmin = $user->role === Roles::GeneralAdmin();
        $isCityAdmin = $user->role === Roles::CityAdmin();
        
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('general.labels.service_name'))
                            ->required()
                            ->maxLength(255),
                            
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->label(__('general.services_module.category'))
                            ->required()
                            ->searchable()
                            ->preload(),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('general.services_module.active'))
                            ->default(true)
                            ->visible($isGeneralAdmin),
                            
                        Forms\Components\TextInput::make('initial_price')
                            ->label(__('general.services_module.initial_price'))
                            ->numeric()
                            ->prefix('SYP')
                            ->required($isGeneralAdmin && !$form->getRecord())
                            ->visible($isGeneralAdmin)
                            ->helperText('سيتم تعيين هذا السعر لجميع المدن بشكل افتراضي'),
                            
                        Forms\Components\TextInput::make('city_price')
                            ->label(__('general.services_module.price_for_city') . ' (' . ($user->city?->getTranslation('name', 'ar') ?? '') . ')')
                            ->numeric()
                            ->prefix('SYP')
                            ->required($isCityAdmin)
                            ->visible($isCityAdmin)
                            ->helperText('سعر هذه الخدمة في مدينتك'),
                    ])->columns(2),
                    
                Forms\Components\Section::make(__('general.services_module.city_prices'))
                    ->schema([
                        Repeater::make('city_prices')
                            ->relationship('cityPrices')
                            ->schema([
                                Forms\Components\Select::make('city_id')
                                    ->label(__('general.labels.city'))
                                    ->options(City::where('code', '!=', 'na')->pluck('name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->distinct()
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('price')
                                    ->label(__('general.services_module.price'))
                                    ->numeric()
                                    ->required()
                                    ->prefix('SYP')
                                    ->columnSpan(1),
                                Forms\Components\Toggle::make('is_active')
                                    ->label(__('general.services_module.active'))
                                    ->default(true)
                                    ->columnSpan(1),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel(__('general.services_module.add_city_price'))
                            ->visible($isGeneralAdmin)
                            ->columnSpanFull(),
                    ])
                    ->visible($isGeneralAdmin),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        $isGeneralAdmin = $user->role === Roles::GeneralAdmin();
        $isCityAdmin = $user->role === Roles::CityAdmin();
        $cityId = $user->city_id;
        
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('general.labels.service_name'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('category.unit')
                    ->label(__('general.services_module.unit'))
                    ->formatStateUsing(fn ($state) => __('general.services_module.units.' . $state))
                    ->badge()
                    ->color('info'),
                    
                TextColumn::make('price_display')
                    ->label(__('general.services_module.price'))
                    ->state(function (Service $record) use ($isCityAdmin, $cityId, $isGeneralAdmin) {
                        if ($isCityAdmin && $cityId) {
                            $price = $record->getPriceForCity($cityId);
                            return $price ? number_format($price, 2) . ' SYP' : 'غير محدد';
                        }
                        
                        if ($isGeneralAdmin) {
                            return 'عبر المدن';
                        }
                        
                        return '-';
                    })
                    ->color(fn ($state) => $state !== 'غير محدد' && $state !== 'عبر المدن' ? 'success' : 'warning'),
                    
                TextColumn::make('average_price')
                    ->label(__('general.services_module.average_price'))
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 2) . ' SYP' : '-')
                    ->color('info')
                    ->visible($isGeneralAdmin),
                    
                TextColumn::make('cities_count')
                    ->label(__('general.services_module.cities_count'))
                    ->state(fn (Service $record) => $record->cityPrices()->where('is_active', true)->count())
                    ->badge()
                    ->visible($isGeneralAdmin),
                    
                TextColumn::make('category.name')
                    ->label(__('general.services_module.category'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                TextColumn::make('created_at')
                    ->label(__('general.labels.created_at'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    
                Filter::make('has_price_in_city')
                    ->label(__('general.services_module.has_price_in_my_city'))
                    ->form([
                        Forms\Components\Toggle::make('has_price')
                            ->label(__('general.services_module.show_only_with_price'))
                            ->default(false),
                    ])
                    ->columnSpan(1)
                    ->visible($isCityAdmin)
                    ->query(function (Builder $query, array $data) use ($cityId) {
                        if (!isset($data['has_price']) || !$data['has_price']) {
                            return $query;
                        }
                        
                        return $query->whereHas('cityPrices', function ($q) use ($cityId) {
                            $q->where('city_id', $cityId)->where('is_active', true);
                        });
                    }),
                    
                Filter::make('missing_price_in_city')
                    ->label(__('general.services_module.services_without_price'))
                    ->form([
                        Forms\Components\Toggle::make('missing_price')
                            ->label(__('general.services_module.show_without_price'))
                            ->default(false),
                    ])
                    ->columnSpan(1)
                    ->visible($isCityAdmin)
                    ->query(function (Builder $query, array $data) use ($cityId) {
                        if (!isset($data['missing_price']) || !$data['missing_price']) {
                            return $query;
                        }
                        
                        return $query->whereDoesntHave('cityPrices', function ($q) use ($cityId) {
                            $q->where('city_id', $cityId)->where('is_active', true);
                        });
                    }),
            ])
            ->filtersTriggerAction(fn (Action $action) => $action->button()->label(''))
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => $isGeneralAdmin || $isCityAdmin),
                    
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => $isGeneralAdmin)
                    ->requiresConfirmation(),
                    
                Tables\Actions\Action::make('remove_from_city')
                    ->label(__('general.services_module.remove_from_city'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible($isCityAdmin)
                    ->requiresConfirmation()
                    ->action(function (Service $record) use ($cityId) {
                        $record->cityPrices()
                            ->where('city_id', $cityId)
                            ->delete();
                            
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title(__('general.services_module.service_removed_from_city'))
                            ->send();
                    })
                    ->modalHeading(__('general.services_module.remove_service'))
                    ->modalDescription(__('general.services_module.remove_service_confirmation')),
                    
                Tables\Actions\Action::make('edit_city_price')
                    ->label(__('general.services_module.edit_city_price'))
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible($isCityAdmin)
                    ->form([
                        Forms\Components\TextInput::make('price')
                            ->label(__('general.services_module.price'))
                            ->numeric()
                            ->required()
                            ->prefix('SYP')
                            ->default(fn (Service $record) => $record->getPriceForCity($cityId)),
                    ])
                    ->action(function (array $data, Service $record) use ($cityId) {
                        $cityPrice = $record->cityPrices()->firstOrNew(['city_id' => $cityId]);
                        $cityPrice->price = $data['price'];
                        $cityPrice->is_active = true;
                        $cityPrice->save();
                        
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title(__('general.services_module.price_updated'))
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => $isGeneralAdmin),
                ]),
            ])
            ->paginated([20, 50, 100, 200]);
    }
    
    public static function getPages(): array
    {
        return [
            'index' => ServiceResource\Pages\ListServices::route('/'),
            'create' => ServiceResource\Pages\CreateService::route('/create'),
            'edit' => ServiceResource\Pages\EditService::route('/{record}/edit'),
        ];
    }
    
    public static function canCreate(): bool
    {
        $user = Auth::user();
        return $user->role === Roles::GeneralAdmin();
    }
    
    public static function canEdit($record): bool
    {
        $user = Auth::user();
        return $user->role === Roles::GeneralAdmin();
    }
    
    public static function canDelete($record): bool
    {
        $user = Auth::user();
        return $user->role === Roles::GeneralAdmin();
    }
}