<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Enums\Roles;
use App\Filament\Resources\UserResource\Pages;
use App\Models\Center;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user';
    protected static ?int $navigationSort = 7;

    public static function getNavigationLabel(): string
    {
        return __('general.labels.users');
    }

    public static function getModelLabel(): string
    {
        return __('general.labels.user');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.labels.users');
    }
    public static function query(Builder $query): Builder
    {
        $user = auth()->user();

        if ($user->role === Roles::GeneralAdmin() || $user->role === Roles::Observer()) {
            return $query; // See all users
        } elseif ($user->role === Roles::CityAdmin()) {
            return $query->where('city_id', $user->city_id);
        } elseif ($user->role === Roles::CenterAdmin()) {
            return $query->where('center_id', $user->center_id);
        }

        return $query;
    }


    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('general.labels.name'))
                ->required(),

            Select::make('role')
                ->label(__('general.labels.user_role'))
                ->options(__('general.roles'))
                ->disabled(fn () => !auth()->user()->role === Roles::GeneralAdmin())

                ->required(),

            TextInput::make('email')
                ->label(__('general.labels.email'))
                ->email()
                ->unique(ignoreRecord: true)
                ->required(),

            TextInput::make('password')
                ->label(__('general.labels.password'))
                ->password()
                ->required(),

            // City Selection
            Select::make('city_id')
                ->label(__('general.labels.city_for_admins'))
                ->relationship(name: 'city', titleAttribute: 'name')
                ->searchable()
                ->preload()
                ->live()
                ->required(fn ($get) => in_array($get('role'), [Roles::CenterAdmin(), Roles::CityAdmin()])),

            // Center Selection (Filtered by Selected City)
            Select::make('center_id')
                ->label(__('general.labels.center_for_admins'))
                ->searchable()
                ->preload()
                ->options(fn ($get) => Center::where('city_id', $get('city_id'))->pluck('name', 'id')) // 👈 Filters centers based on selected city
                ->required(fn ($get) => $get('role') === 'center_admin'),

            Toggle::make('is_active')
                ->label(__('general.labels.active_user'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('general.labels.full_name'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('email')
                    ->label(__('general.labels.email'))
                    ->sortable()
                    ->searchable(),

                BadgeColumn::make('role')
                ->formatStateUsing(fn ($state) => __('general.roles.'.$state))
                ->label(__('general.labels.role'))
                  ,

                TextColumn::make('city.name')
                    ->label(__('general.labels.city'))
                    ->sortable()
                    ->searchable(),
//                    ->hidden(fn ($record) => $record->role !== 'city_admin'),

                TextColumn::make('center.name')
                    ->label(__('general.labels.center'))
                    ->sortable()
                    ->searchable(),
//                    ->hidden(fn ($record) => $record->role !== 'center_admin'),
                CheckboxColumn::make('is_active')->disabled(fn() => auth()->user()->role === Roles::Observer()),

            ])
            ->filters([
                SelectFilter::make('role')
                    ->options(__('general.roles'))
                    ->label(__('general.labels.filter_by_role'))
                   ,
                SelectFilter::make('city_id')
                    ->relationship('city', 'name')
                    ->label(__('general.labels.filter_by_city'))
                ,
                SelectFilter::make('center_id')
                    ->relationship('center', 'name')
                    ->label(__('general.labels.filter_by_center'))
                ,

                SelectFilter::make('is_active')
                    ->label(__('general.labels.filter_by_status'))
                    ->options([
                        true => __('general.labels.active'),
                        false => __('general.labels.inactive'),
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->hidden(fn() => auth()->user()->role === Roles::Observer()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->hidden(fn() => auth()->user()->role === Roles::Observer()),
                ])->hidden(fn() => auth()->user()->role === Roles::Observer()),
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return auth()->user()->role === Roles::GeneralAdmin() && auth()->user()->role !== Roles::Observer();
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->role === Roles::GeneralAdmin() && auth()->user()->role !== Roles::Observer();
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->role === Roles::GeneralAdmin() && auth()->user()->role !== Roles::Observer();
    }
    public static function canViewAny(): bool
    {
        return auth()->user()->role === Roles::GeneralAdmin() || auth()->user()->role === Roles::Observer();
    }
}
