<?php

namespace App\Filament\Resources;

use App\Enums\Roles;
use App\Filament\Resources\SocialLinkResource\Pages;
use App\Models\SocialLink;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SocialLinkResource extends Resource
{
    protected static ?string $model = SocialLink::class;
    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()->role === Roles::GeneralAdmin() || auth()->user()->role === Roles::CityAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('city')
                ->required()
                ->disabledOn('edit')
                ->default(auth()->user()->city),

            Forms\Components\TextInput::make('facebook')->label('Facebook URL')->url(),
            Forms\Components\TextInput::make('instagram')->label('Instagram URL')->url(),
            Forms\Components\TextInput::make('whatsapp')->label('WhatsApp URL')->url(),
            Forms\Components\TextInput::make('telegram')->label('Telegram URL')->url(),
            Forms\Components\TextInput::make('website')->label('Website URL')->url(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('city')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('facebook')->label('Facebook')->limit(30),
                Tables\Columns\TextColumn::make('instagram')->label('Instagram')->limit(30),
                Tables\Columns\TextColumn::make('whatsapp')->label('WhatsApp')->limit(30),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => auth()->user()->role === Roles::GeneralAdmin()),
            ])
            ->filters([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSocialLinks::route('/'),
            'create' => Pages\CreateSocialLink::route('/create'),
            'edit' => Pages\EditSocialLink::route('/{record}/edit'),
        ];
    }
}
