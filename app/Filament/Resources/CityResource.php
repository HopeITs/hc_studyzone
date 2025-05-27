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

    public static function canViewAny(): bool
    {
        return auth()->user()->role === Roles::GeneralAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TranslatableContainer::make(
                Forms\Components\TextInput::make('name')->required()
            ),
            Forms\Components\TextInput::make('code')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('code')->sortable(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('Manage Social Links')
                    ->label('Social Links')
                    ->icon('heroicon-o-link')
                    ->url(fn ($record) =>
                        $record->socialLink
                            ? route('filament.admin.resources.social-links.edit', ['record' => $record->socialLink->id])
                            : route('filament.admin.resources.social-links.create', ['city' => $record->name])
                    )
                    ->visible(fn ($record) =>
                        auth()->user()->role === Roles::GeneralAdmin() ||
                        auth()->user()->city === $record->name
                    )
                    ->color('info'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
