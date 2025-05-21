<?php

namespace App\Filament\Resources;

use App\Enums\Roles;
use App\Filament\Resources\UserResource\Pages;
use App\Models\Center;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $label = 'مستخدم';

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $navigationLabel = 'المستخدمون';

    protected static ?string $pluralModelLabel = 'المستخدمون';
    protected static ?int $navigationSort = 7;
    public static function query(Builder $query): Builder
    {
        $user = auth()->user();

        if ($user->role === Roles::GeneralAdmin()) {
            return $query; // See all users
        } elseif ($user->role === Roles::CityAdmin()) {
            return $query->where('city_id', $user->city_id);
        } elseif ($user->role === Roles::CenterAdmin()) {
            return $query->where('center_id', $user->center_id);
        }

        return $query;
    }


    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Name')
                ->required(),

            Select::make('roles')
                ->label('User Role')
                ->relationship('roles', 'name')
                ->disabled(fn () => !auth()->user()->role === Roles::GeneralAdmin())

                ->required(),

            TextInput::make('email')
                ->label('Email')
                ->email()
                ->unique(ignoreRecord: true)
                ->required(),

            TextInput::make('password')
                ->label('Password')
                ->password()
                ->required(),

            // City Selection
            Select::make('city_id')
                ->label('City (For City Admins)')
                ->relationship(name: 'city', titleAttribute: 'name')
                ->searchable()
                ->preload()
                ->live()
                ->required(fn ($get) => in_array($get('roles'), ['city_admin', 'center_admin'])),

            // Center Selection (Filtered by Selected City)
            Select::make('center_id')
                ->label('Center (For Center Admins)')
                ->searchable()
                ->preload()
                ->options(fn ($get) => Center::where('city_id', $get('city_id'))->pluck('name', 'id')) // 👈 Filters centers based on selected city
                ->required(fn ($get) => $get('roles') === 'center_admin'),

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

                BadgeColumn::make('roles.name')
                    ->label('Role')
                  ,

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
                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->label('Filter by Role')
                   ,
                SelectFilter::make('city_id')
                    ->relationship('city', 'name')
                    ->label('Filter by City')
                ,
                SelectFilter::make('center_id')
                    ->relationship('center', 'name')
                    ->label('Filter by Center')
                ,

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

    public static function canCreate(): bool
    {
        return auth()->user()->role === Roles::GeneralAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->role === Roles::GeneralAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->role === Roles::GeneralAdmin();
    }
    public static function canViewAny(): bool
    {
        return auth()->user()->role === Roles::GeneralAdmin();
    }
}
