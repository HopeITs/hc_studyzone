<?php

namespace App\Filament\Resources;

use App\Models\Service;
use App\Models\ServiceCategory;
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
use Illuminate\Database\Eloquent\Builder;

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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('general.labels.firstname'))
                            ->required(),

                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->label(__('general.services_module.unit'))
                            ->required(),

                        Forms\Components\TextInput::make('current_price')
                            ->label(__('general.services_module.current_price'))
                            ->placeholder(fn ($record) => $record?->currentPrice?->price ?? __('general.services_module.no_price_set'))
                            ->disabled()
                            ->dehydrated(false)
                            ->suffix('SYP'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('general.labels.firstname'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('category.unit')
                    ->label(__('general.services_module.unit'))
                    ->formatStateUsing(fn ($state) => __('general.services_module.units.' . $state))
                    ->badge()
                    ->color('info'),

                TextColumn::make('currentPrice.price')
                    ->label(__('general.services_module.price'))
                    ->suffix(' SYP')
                    ->color('success')
                    ->placeholder(__('general.services_module.n_a')),
            ])
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersFormColumns(4)
            ->filters([
                SelectFilter::make('category_id')
                    ->label(__('general.services_module.unit'))
                    ->relationship('category', 'name')
                    ->searchable()
                    ->multiple()
                    ->preload()
                    ->columnSpan(1),
                Filter::make('has_price')
                    ->label('حالة السعر')
                    ->form([
                        \Filament\Forms\Components\Toggle::make('has_price')
                            ->label('خدمات بدون سعر')
                            ->default(false),
                    ])
                    ->columnSpan(1)
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['has_price']) || !$data['has_price']) {
                            return $query;
                        }
                        
                        return $query->whereDoesntHave('currentPrice');
                    }),
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
            'index' => ServiceResource\Pages\ListServices::route('/'),
            'create' => ServiceResource\Pages\CreateService::route('/create'),
            'edit' => ServiceResource\Pages\EditService::route('/{record}/edit'),
        ];
    }
}