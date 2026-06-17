<?php

namespace App\Filament\Resources;

use App\Enums\Roles;
use App\Filament\Resources\CityResource\Pages;
use App\Models\City;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Mvenghaus\FilamentPluginTranslatableInline\Forms\Components\TranslatableContainer;

class CityResource extends Resource
{
    protected static ?string $model = City::class;
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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

    public static function form(Form $form): Form
    {
        return $form->schema([TranslatableContainer::make(Forms\Components\TextInput::make('name')->required()), Forms\Components\TextInput::make('code')->required(), Forms\Components\TextInput::make('facebook')->label(__('general.labels.facebook_url'))->url(), Forms\Components\TextInput::make('instagram')->label(__('general.labels.instagram_url'))->url(), Forms\Components\TextInput::make('whatsapp')->label(__('general.labels.whatsapp_url'))->url(), Forms\Components\TextInput::make('telegram')->label(__('general.labels.telegram_url'))->url(), Forms\Components\TextInput::make('website')->label(__('general.labels.website_url'))->url()]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([Tables\Columns\TextColumn::make('name')->sortable()->searchable(), Tables\Columns\TextColumn::make('code')->sortable()])
            ->actions([ViewAction::make(), EditAction::make()->hidden(fn() => auth()->user()->role === Roles::Observer()), Action::make('Manage Social Links')->label(__('general.labels.social_links'))->icon('heroicon-o-link')->url(fn($record) => $record->socialLink ? route('filament.admin.resources.social-links.edit', ['record' => $record->socialLink->id]) : route('info.show', ['city' => $record->code]))->visible(fn($record) => (auth()->user()->role === Roles::GeneralAdmin() || auth()->user()->city === $record->name) && auth()->user()->role !== Roles::Observer())->color('info')])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()->hidden(fn() => auth()->user()->role === Roles::Observer())])->hidden(fn() => auth()->user()->role === Roles::Observer())]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCities::route('/'),
        ];
    }
}
