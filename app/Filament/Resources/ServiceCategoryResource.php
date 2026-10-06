<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ServiceCategoryResource\Pages\ListServiceCategories;
use App\Enums\Roles;
use App\Models\ServiceCategory;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ServiceCategoryResource extends Resource
{
    protected static ?string $model = ServiceCategory::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';


    public static function getNavigationLabel(): string
    {
        return __('general.labels.service_categories');
    }

    public static function getModelLabel(): string
    {
        return __('general.labels.service_category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.labels.service_categories');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        if ($user->role === Roles::GeneralAdmin()) {
            return parent::getEloquentQuery();
        }

        return parent::getEloquentQuery()->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('unit')
                    ->label(__('general.labels.unit'))
                    ->required()
                    ->options(__('general.services_module.units')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('unit')
                    ->badge()
                    ->formatStateUsing(fn ($state) => __('general.services_module.units.'.$state) !== 'general.services_module.units.'.$state ? __('general.services_module.units.'.$state) : $state),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceCategories::route('/'),

        ];
    }

    public static function canCreate(): bool
    {
        return Auth::user()->role === Roles::GeneralAdmin();
    }

      public static function canViewAny(): bool
    {
        return auth()->user()->role === Roles::GeneralAdmin();
    }

    public static function canEdit($record): bool
    {
        return Auth::user()->role === Roles::GeneralAdmin();
    }

    public static function canDelete($record): bool
    {
        return Auth::user()->role === Roles::GeneralAdmin();
    }
}
