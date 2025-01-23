<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Full Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->required(),

                Select::make('role')
                    ->label('User Role')
                    ->options([
                        'general_admin' => 'General Admin',
                        'city_admin' => 'City Admin',
                        'center_admin' => 'Center Admin',
                    ])
                    ->required(),

                Select::make('city_id')
                    ->label('City (For City Admins)')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload()
                    ->hidden(fn ($get) => $get('role') !== 'city_admin'),

                Select::make('center_id')
                    ->label('Center (For Center Admins)')
                    ->relationship('center', 'name')
                    ->searchable()
                    ->preload()
                    ->hidden(fn ($get) => $get('role') !== 'center_admin'),

                Toggle::make('is_active')
                    ->label('Active User')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Full Name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->sortable()
                    ->searchable(),

                BadgeColumn::make('role')
                    ->label('Role')
                    ->colors([
                        'primary' => 'general_admin',
                        'warning' => 'city_admin',
                        'success' => 'center_admin',
                    ]),

                TextColumn::make('city.name')
                    ->label('City')
                    ->sortable()
                    ->searchable(),
//                    ->hidden(fn ($record) => $record->role !== 'city_admin'),

                TextColumn::make('center.name')
                    ->label('Center')
                    ->sortable()
                    ->searchable(),
//                    ->hidden(fn ($record) => $record->role !== 'center_admin'),

                BadgeColumn::make('is_active')
                    ->label('Status')
                    ->colors([
                        'success' => true,
                        'danger' => false,
                    ])
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Filter by Role')
                    ->options([
                        'general_admin' => 'General Admin',
                        'city_admin' => 'City Admin',
                        'center_admin' => 'Center Admin',
                    ]),

                SelectFilter::make('is_active')
                    ->label('Filter by Status')
                    ->options([
                        true => 'Active',
                        false => 'Inactive',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
