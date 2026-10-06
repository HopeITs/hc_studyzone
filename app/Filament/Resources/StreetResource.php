<?php

namespace App\Filament\Resources;

use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\StreetResource\Pages\ListStreets;
use App\Enums\Roles;
use App\Filament\Resources\StreetResource\Pages;
use App\Filament\Resources\StreetResource\RelationManagers;
use App\Models\City;
use App\Models\Street;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Forms\Components\TranslatableFields;

class StreetResource extends Resource
{
    protected static ?string $model = Street::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 3;
    use Translatable;

    public static function getNavigationLabel(): string
    {
        return __('general.labels.streets');
    }

    public static function getModelLabel(): string
    {
        return __('general.labels.street_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.labels.streets');
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function canCreate(): bool
    {
        return auth()->user()->role === Roles::GeneralAdmin() && auth()->user()->role !== Roles::Observer();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->role === Roles::GeneralAdmin() || auth()->user()->role === Roles::Observer();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('city_id')->relationship('city', 'name')->required(),
            TranslatableFields::mainLocaleRequired(TextInput::make('name')->maxLength(255)->required()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([TextColumn::make('name')->label(__('general.labels.name'))->formatStateUsing(fn(Model $record) => $record->getTranslation('name', 'ar') . ' - ' . $record->getTranslation('name', 'en'))->sortable()->searchable(), TextColumn::make('city.name')->sortable()->searchable()])
            ->filters([
                SelectFilter::make('city_id')
                    ->label(__('general.labels.filter_by_city'))
                    ->options(City::all()->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()->hidden(fn() => auth()->user()->role === Roles::Observer())])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()->hidden(fn() => auth()->user()->role === Roles::Observer())])->hidden(fn() => auth()->user()->role === Roles::Observer())]);
    }

    public static function getRelations(): array
    {
        return [
                //
            ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStreets::route('/'),
        ];
    }
}
