<?php

namespace App\Filament\Resources;

use App\Enums\Roles;
use App\Filament\Resources\CenterResource\Pages;
use App\Filament\Resources\CenterResource\RelationManagers;
use App\Models\Center;
use App\Models\City;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Mvenghaus\FilamentPluginTranslatableInline\Forms\Components\TranslatableContainer;

class CenterResource extends Resource
{

    use Translatable;

    protected static ?string $model = Center::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $label = 'مركز';

    protected static ?string $modelLabel = 'مركز';

    protected static ?string $navigationLabel = 'المراكز';

    protected static ?string $pluralModelLabel = 'المراكز';
    protected static ?int $navigationSort = 4;

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
            Forms\Components\TextInput::make('phone')->required(),
            Forms\Components\TextInput::make('address')->required(),
            Forms\Components\Select::make('city_id')
                ->relationship('city', 'name')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByDesc('is_active')->orderBy('city_id'))
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('city.name')->sortable()->searchable(),
                TextColumn::make('phone'),
                TextColumn::make('entries_count')->counts('entries')->label('Total Entries Count')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('users_count')->counts('users')->label('Employees Count')->sortable()->toggleable(isToggledHiddenByDefault: true),
                CheckboxColumn::make('is_active'),
                TextColumn::make('address')->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('city_id')
                    ->label('Filter by City')
                    ->options(City::all()->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ])->icon('heroicon-m-ellipsis-horizontal'),
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
            'index' => Pages\ListCenters::route('/'),

        ];
    }
}
