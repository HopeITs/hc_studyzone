<?php

namespace App\Filament\Resources;

use App\Enums\Roles;
use App\Filament\Resources\StreetResource\Pages;
use App\Filament\Resources\StreetResource\RelationManagers;
use App\Models\City;
use App\Models\Street;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Mvenghaus\FilamentPluginTranslatableInline\Forms\Components\TranslatableContainer;

class StreetResource extends Resource
{
    protected static ?string $model = Street::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('city_id')->relationship('city', 'name')->required(),
            TranslatableContainer::make(Forms\Components\TextInput::make('name')->maxLength(255)->required())
                ->onlyMainLocaleRequired() 
                ->requiredLocales(['en', 'ar']), 
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([Tables\Columns\TextColumn::make('name')->label(__('general.labels.name'))->formatStateUsing(fn(Model $record) => $record->getTranslation('name', 'ar') . ' - ' . $record->getTranslation('name', 'en'))->sortable()->searchable(), Tables\Columns\TextColumn::make('city.name')->sortable()->searchable()])
            ->filters([
                SelectFilter::make('city_id')
                    ->label(__('general.labels.filter_by_city'))
                    ->options(City::all()->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->actions([ViewAction::make(), Tables\Actions\EditAction::make()->hidden(fn() => auth()->user()->role === Roles::Observer())])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()->hidden(fn() => auth()->user()->role === Roles::Observer())])->hidden(fn() => auth()->user()->role === Roles::Observer())]);
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
            'index' => Pages\ListStreets::route('/'),
        ];
    }
}
