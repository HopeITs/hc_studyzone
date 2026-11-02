<?php

namespace App\Filament\Resources;

use App\Enums\InventoryUnit;
use App\Enums\Roles;
use App\Filament\Resources\InventoryItemResource\Pages\CreateInventoryItem;
use App\Filament\Resources\InventoryItemResource\Pages\EditInventoryItem;
use App\Filament\Resources\InventoryItemResource\Pages\ListInventoryItems;
use App\Filament\Resources\InventoryItemResource\RelationManagers\MovementsRelationManager;
use App\Models\InventoryItem;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryItemResource extends Resource
{
    protected static ?string $model = InventoryItem::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|\UnitEnum|null $navigationGroup = 'النظام المالي';

    public static function getNavigationLabel(): string
    {
        return __('inventory.items');
    }

    public static function getPluralLabel(): string
    {
        return __('inventory.items');
    }

    public static function getModelLabel(): string
    {
        return __('inventory.item');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();
        $query = parent::getEloquentQuery();

        return match (true) {
            $user->role === Roles::GeneralAdmin(),
            $user->role === Roles::Observer() => $query,

            $user->role === Roles::CityAdmin() => $query->whereHas(
                'center',
                fn ($q) => $q->where('city_id', $user->city_id)
            ),

            $user->role === Roles::CenterAdmin() => $query->where('center_id', $user->center_id),

            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * SQL constraint on the current stock (sum of signed movements):
     * 'out' = none left, 'below' = some left but at or under the threshold,
     * 'in' = above the threshold, 'low' = 'out' or 'below' combined.
     * Raw SQL bypasses the automatic table prefixing, so prefix the names explicitly.
     */
    protected static function applyStockStatusConstraint(Builder $query, string $status): Builder
    {
        $prefix = DB::getTablePrefix();
        $stock = "(select coalesce(sum(m.quantity), 0) from {$prefix}stock_movements m where m.inventory_item_id = {$prefix}inventory_items.id)";
        $threshold = "{$prefix}inventory_items.min_stock_threshold";

        return match ($status) {
            'out' => $query->whereRaw("{$stock} <= 0"),
            'below' => $query->whereRaw("{$stock} > 0 and {$stock} <= {$threshold}"),
            'in' => $query->whereRaw("{$stock} > {$threshold}"),
            default => $query->whereRaw("{$stock} <= {$threshold}"),
        };
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::applyStockStatusConstraint(
            static::getEloquentQuery()->where('is_active', true),
            'low'
        )->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        $user = Auth::user();
        $isGeneralAdmin = $user->role === Roles::GeneralAdmin();

        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('inventory.fields.name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('category')
                            ->label(__('inventory.fields.category'))
                            ->maxLength(255),

                        Select::make('unit')
                            ->label(__('inventory.fields.unit'))
                            ->options(
                                collect(InventoryUnit::cases())
                                    ->mapWithKeys(fn (InventoryUnit $unit) => [
                                        $unit->value => __('inventory.units.'.$unit->value),
                                    ])
                                    ->all()
                            )
                            ->default(InventoryUnit::default()->value)
                            ->required(),

                        Select::make('center_id')
                            ->label(__('inventory.fields.center'))
                            ->relationship('center', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->visible($isGeneralAdmin),

                        TextInput::make('purchase_cost')
                            ->label(__('inventory.fields.purchase_cost'))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->suffix('SYP')
                            ->helperText(__('inventory.helpers.old_currency')),

                        TextInput::make('sale_price')
                            ->label(__('inventory.fields.sale_price'))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->suffix('SYP')
                            ->helperText(__('inventory.helpers.old_currency')),

                        TextInput::make('min_stock_threshold')
                            ->label(__('inventory.fields.min_stock_threshold'))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->required(),

                        TextInput::make('supplier')
                            ->label(__('inventory.fields.supplier'))
                            ->maxLength(255),

                        DatePicker::make('last_purchase_date')
                            ->label(__('inventory.fields.last_purchase_date')),

                        Toggle::make('is_active')
                            ->label(__('inventory.fields.is_active'))
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        $isGeneralAdmin = $user->role === Roles::GeneralAdmin();
        $isCityAdmin = $user->role === Roles::CityAdmin();
        $canManage = $isGeneralAdmin || $user->role === Roles::CenterAdmin();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withSum('movements as stock_sum', 'quantity')
                ->with('center'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('inventory.fields.name'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('category')
                    ->label(__('inventory.fields.category'))
                    ->sortable()
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('unit')
                    ->label(__('inventory.fields.unit'))
                    ->formatStateUsing(fn (InventoryUnit $state) => __('inventory.units.'.$state->value))
                    ->badge()
                    ->color('info'),

                TextColumn::make('sale_price')
                    ->label(__('inventory.fields.sale_price'))
                    ->suffix(' SYP')
                    ->sortable(),

                TextColumn::make('purchase_cost')
                    ->label(__('inventory.fields.purchase_cost'))
                    ->suffix(' SYP')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('stock_sum')
                    ->label(__('inventory.fields.current_stock'))
                    ->state(fn (InventoryItem $record) => (int) ($record->stock_sum ?? 0))
                    ->badge()
                    ->color(function (InventoryItem $record) {
                        $stock = (int) ($record->stock_sum ?? 0);

                        return match (true) {
                            $stock <= 0 => 'gray',
                            $stock <= $record->min_stock_threshold => 'danger',
                            default => 'success',
                        };
                    })
                    ->sortable(),

                TextColumn::make('min_stock_threshold')
                    ->label(__('inventory.fields.min_stock_threshold'))
                    ->sortable(),

                TextColumn::make('supplier')
                    ->label(__('inventory.fields.supplier'))
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('last_purchase_date')
                    ->label(__('inventory.fields.last_purchase_date'))
                    ->date('Y-m-d')
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('center.name')
                    ->label(__('inventory.fields.center'))
                    ->sortable()
                    ->visible($isGeneralAdmin || $isCityAdmin),

                IconColumn::make('is_active')
                    ->label(__('inventory.fields.is_active'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->sortable(),
            ])
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersFormColumns(3)
            ->filters([
                SelectFilter::make('center_id')
                    ->label(__('inventory.fields.center'))
                    ->relationship(
                        'center',
                        'name',
                        fn ($query) => $isCityAdmin ? $query->where('city_id', $user->city_id) : $query
                    )
                    ->searchable()
                    ->multiple()
                    ->preload()
                    ->visible($isGeneralAdmin || $isCityAdmin)
                    ->columnSpan(1),

                SelectFilter::make('category')
                    ->label(__('inventory.filters.category'))
                    ->options(
                        fn () => static::getEloquentQuery()
                            ->whereNotNull('category')
                            ->distinct()
                            ->orderBy('category')
                            ->pluck('category', 'category')
                            ->all()
                    )
                    ->searchable()
                    ->columnSpan(1),

                SelectFilter::make('unit')
                    ->label(__('inventory.filters.unit'))
                    ->options(
                        collect(InventoryUnit::cases())
                            ->mapWithKeys(fn (InventoryUnit $unit) => [
                                $unit->value => __('inventory.units.'.$unit->value),
                            ])
                            ->all()
                    )
                    ->columnSpan(1),

                TernaryFilter::make('is_active')
                    ->label(__('inventory.fields.is_active'))
                    ->placeholder(__('inventory.filters.all'))
                    ->trueLabel(__('inventory.filters.active'))
                    ->falseLabel(__('inventory.filters.inactive'))
                    ->columnSpan(1),

                SelectFilter::make('stock_status')
                    ->label(__('inventory.filters.stock_status'))
                    ->options([
                        'below' => __('inventory.filters.below_threshold'),
                        'in' => __('inventory.filters.in_stock'),
                        'out' => __('inventory.filters.out_of_stock'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return filled($data['value'] ?? null)
                            ? static::applyStockStatusConstraint($query, $data['value'])
                            : $query;
                    })
                    ->columnSpan(1),
            ])
            ->recordActions([
                Action::make('addPurchase')
                    ->label(__('inventory.actions.add_purchase'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => $canManage)
                    ->modalHeading(__('inventory.actions.add_purchase'))
                    ->schema([
                        TextInput::make('quantity')
                            ->label(__('inventory.fields.quantity'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required(),

                        TextInput::make('cost')
                            ->label(__('inventory.fields.total_cost'))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->suffix('SYP')
                            ->helperText(__('inventory.helpers.old_currency'))
                            ->required(),

                        TextInput::make('supplier')
                            ->label(__('inventory.fields.supplier'))
                            ->maxLength(255)
                            ->default(fn (InventoryItem $record) => $record->supplier),

                        Textarea::make('note')
                            ->label(__('inventory.fields.note'))
                            ->maxLength(500),
                    ])
                    ->action(function (array $data, InventoryItem $record): void {
                        $record->recordPurchase(
                            quantity: (int) $data['quantity'],
                            cost: (int) $data['cost'],
                            supplier: $data['supplier'] ?? null,
                            note: $data['note'] ?? null,
                            userId: Auth::id(),
                        );

                        Notification::make()
                            ->success()
                            ->title(__('inventory.messages.purchase_recorded'))
                            ->send();
                    }),

                Action::make('adjustStock')
                    ->label(__('inventory.actions.adjustment'))
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->visible(fn () => $canManage)
                    ->modalHeading(__('inventory.actions.adjustment'))
                    ->schema([
                        TextInput::make('counted_quantity')
                            ->label(__('inventory.fields.counted_quantity'))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->helperText(__('inventory.helpers.counted_quantity'))
                            ->required(),

                        Textarea::make('note')
                            ->label(__('inventory.fields.note'))
                            ->maxLength(500)
                            ->required(),
                    ])
                    ->action(function (array $data, InventoryItem $record): void {
                        $movement = $record->recordCountAdjustment(
                            countedQuantity: (int) $data['counted_quantity'],
                            note: $data['note'],
                            userId: Auth::id(),
                        );

                        if ($movement === null) {
                            Notification::make()
                                ->warning()
                                ->title(__('inventory.messages.adjustment_no_difference'))
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title(__('inventory.messages.adjustment_recorded'))
                            ->send();
                    }),

                EditAction::make()
                    ->visible(fn () => $canManage),

                DeleteAction::make()
                    ->visible(fn () => $isGeneralAdmin),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => $isGeneralAdmin),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MovementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryItems::route('/'),
            'create' => CreateInventoryItem::route('/create'),
            'edit' => EditInventoryItem::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return in_array(Auth::user()->role, [
            Roles::GeneralAdmin(),
            Roles::CityAdmin(),
            Roles::CenterAdmin(),
            Roles::Observer(),
        ], true);
    }

    public static function canCreate(): bool
    {
        return in_array(Auth::user()->role, [
            Roles::GeneralAdmin(),
            Roles::CenterAdmin(),
        ], true);
    }

    public static function canEdit($record): bool
    {
        return in_array(Auth::user()->role, [
            Roles::GeneralAdmin(),
            Roles::CenterAdmin(),
        ], true);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()->role === Roles::GeneralAdmin();
    }
}
