<?php

namespace App\Filament\Resources;

use App\Enums\Roles;
use App\Filament\Forms\Components\TranslatableFields;
use App\Filament\Resources\CenterResource\Pages\ListCenters;
use App\Models\Center;
use App\Models\City;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class CenterResource extends Resource
{
    use Translatable;

    protected static ?string $model = Center::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('general.labels.centers');
    }

    public static function getModelLabel(): string
    {
        return __('general.labels.center');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.labels.centers');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->role !== Roles::Observer()
            && auth()->user()->role !== Roles::Cashier();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->role === Roles::GeneralAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TranslatableFields::mainLocaleRequired(TextInput::make('name')->required()),
            TextInput::make('phone')->required(),
            TextInput::make('order')->integer()->required(),

            Select::make('city_id')
                ->relationship('city', 'name')
                ->required(),
            TextInput::make('address')->required()->columnSpanFull(),
            Section::make(__('general.labels.center_equipment'))
                ->schema([
                    TextInput::make('seat_capacity')
                        ->label(__('general.labels.seat_capacity'))
                        ->placeholder(__('general.labels.equipment_unknown'))
                        ->integer()
                        ->minValue(0)
                        ->maxValue(65535),
                    Select::make('internet_available')
                        ->label(__('general.labels.center_internet'))
                        ->placeholder(__('general.labels.equipment_unknown'))
                        ->options(self::availabilityOptions()),
                    ...self::equipmentFields('micromotor_count', 'mecrator'),
                    ...self::equipmentFields('drafting_table_count', 'drawer'),
                    ...self::equipmentFields('hama_count', 'hama_equipment'),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    private static function equipmentFields(string $field, string $label): array
    {
        $availability = $field.'_available';

        return [
            Select::make($availability)
                ->label(__('general.labels.'.$label))
                ->placeholder(__('general.labels.equipment_unknown'))
                ->options(self::availabilityOptions())
                ->afterStateHydrated(function (Select $component, ?Center $record) use ($field): void {
                    $count = $record?->getAttribute($field);
                    $component->state($count === null ? null : ((int) $count > 0 ? '1' : '0'));
                })
                ->live()
                ->dehydrated(false),
            TextInput::make($field)
                ->label(__('general.labels.equipment_count'))
                ->integer()
                ->minValue(fn (Get $get): int => (string) $get($availability) === '1' ? 1 : 0)
                ->maxValue(65535)
                ->visible(fn (Get $get): bool => (string) $get($availability) === '1')
                ->required(fn (Get $get): bool => (string) $get($availability) === '1')
                ->dehydratedWhenHidden()
                ->dehydrateStateUsing(fn (Get $get, mixed $state): ?int => match ((string) $get($availability)) {
                    '1' => (int) $state,
                    '0' => 0,
                    default => null,
                }),
        ];
    }

    private static function availabilityOptions(): array
    {
        return [
            '1' => __('general.labels.equipment_available'),
            '0' => __('general.labels.equipment_unavailable'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByDesc('is_active')->orderBy('order')->orderBy('city_id'))
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('city.name')->sortable()->searchable(),
                TextColumn::make('order')->sortable(),
                TextColumn::make('phone'),
                TextColumn::make('entries_count')->counts('entries')->label(__('general.labels.total_entries_count'))->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('users_count')->counts('users')->label(__('general.labels.employees_count'))->sortable()->toggleable(isToggledHiddenByDefault: true),
                CheckboxColumn::make('is_active')->disabled(fn () => auth()->user()->role === Roles::Observer()), TextColumn::make('address')->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true)])
            ->filters([
                SelectFilter::make('city_id')
                    ->label(__('general.labels.filter_by_city'))
                    ->options(City::all()->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->recordActions([ActionGroup::make([ViewAction::make(), EditAction::make()->hidden(fn () => auth()->user()->role === Roles::Observer()), DeleteAction::make()->hidden(fn () => auth()->user()->role === Roles::Observer())])->icon('heroicon-m-ellipsis-horizontal')])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()->hidden(fn () => auth()->user()->role === Roles::Observer())])->hidden(fn () => auth()->user()->role === Roles::Observer())]);
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
            'index' => ListCenters::route('/'),

        ];
    }
}
