<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\CityResource\Pages\ListCities;
use App\Enums\Roles;
use App\Filament\Resources\CityResource\Pages;
use App\Models\City;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Forms\Components\TranslatableFields;

class CityResource extends Resource
{
    protected static ?string $model = City::class;
    protected static ?int $navigationSort = 2;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return __('general.labels.cities');
    }

    public static function getModelLabel(): string
    {
        return __('general.labels.city_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.labels.cities');
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
        return $schema->components([TranslatableFields::make(TextInput::make('name')->required()), TextInput::make('code')->required(), TextInput::make('facebook')->label(__('general.labels.facebook_url'))->url(), TextInput::make('instagram')->label(__('general.labels.instagram_url'))->url(), TextInput::make('whatsapp')->label(__('general.labels.whatsapp_url'))->url(), TextInput::make('telegram')->label(__('general.labels.telegram_url'))->url(), TextInput::make('website')->label(__('general.labels.website_url'))->url()]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([TextColumn::make('name')->sortable()->searchable(), TextColumn::make('code')->sortable()])
            ->recordActions([ViewAction::make(), EditAction::make()->hidden(fn() => auth()->user()->role === Roles::Observer()), Action::make('Manage Social Links')->label(__('general.labels.social_links'))->icon('heroicon-o-link')->url(fn($record) => $record->socialLink ? route('filament.admin.resources.social-links.edit', ['record' => $record->socialLink->id]) : route('info.show', ['city' => $record->code]))->visible(fn($record) => (auth()->user()->role === Roles::GeneralAdmin() || auth()->user()->city === $record->name) && auth()->user()->role !== Roles::Observer())->color('info')])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()->hidden(fn() => auth()->user()->role === Roles::Observer())])->hidden(fn() => auth()->user()->role === Roles::Observer())]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCities::route('/'),
        ];
    }
}
