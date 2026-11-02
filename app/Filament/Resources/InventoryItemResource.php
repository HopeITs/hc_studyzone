<?php

namespace App\Filament\Resources;

use App\Enums\InventoryUnit;
use App\Enums\Roles;
use App\Enums\StockMovementType;
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
     * SQL constraint: current stock (sum of signed movements) at or below the threshold.
     * Raw SQL bypasses the automatic table prefixing, so prefix the names explicitly.
     */
    protected static function applyLowStockConstraint(Builder $query, bool $low = true): Builder
    {
        $prefix = DB::getTablePrefix();
        $operator = $low ? '<=' : '>';

        return $query->whereRaw(
            "(select coalesce(sum(m.quantity), 0) from {$prefix}stock_movements m where m.inventory_item_id = {$prefix}inventory_items.id) {$operator} {$prefix}inventory_items.min_stock_threshold"
        );
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::applyLowStockConstraint(
            static::getEloquentQuery()->where('is_active', true)
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

                TextColumn::make('stock_sum')
                    ->label(__('inventory.fields.current_stock'))
                    ->state(fn (InventoryItem $record) => (int) ($record->stock_sum ?? 0))
                    ->badge()
                    ->color(fn (InventoryItem $record) => (int) ($record->stock_sum ?? 0) <= $record->min_stock_threshold
                        ? 'danger'
                        : 'success')
                    ->sortable(),

                TextColumn::make('min_stock_threshold')
                    ->label(__('inventory.fields.min_stock_threshold'))
                    ->sortable(),

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

                TernaryFilter::make('is_active')
                    ->label(__('inventory.fields.is_active'))
                    ->placeholder(__('inventory.filters.all'))
                    ->trueLabel(__('inventory.filters.active'))
                    ->falseLabel(__('inventory.filters.inactive'))
                    ->columnSpan(1),

                TernaryFilter::make('low_stock')
                    ->label(__('inventory.filters.low_stock'))
                    ->placeholder(__('inventory.filters.all'))
                    ->trueLabel(__('inventory.filters.low_only'))
                    ->falseLabel(__('inventory.filters.ok_only'))
                    ->queries(
                        true: fn (Builder $query) => static::applyLowStockConstraint($query, true),
                        false: fn (Builder $query) => static::applyLowStockConstraint($query, false),
                        blank: fn (Builder $query) => $query,
                    )
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

                        Textarea::make('note')
                            ->label(__('inventory.fields.note'))
                            ->maxLength(500),
                    ])
                    ->action(function (array $data, InventoryItem $record): void {
                        $quantity = (int) $data['quantity'];
                        $cost = (int) $data['cost'];

                        DB::transaction(function () use ($data, $record, $quantity, $cost) {
                            $record->movements()->create([
                                'center_id' => $record->center_id,
                                'user_id' => Auth::id(),
                                'type' => StockMovementType::Purchase,
                                'quantity' => $quantity,
                                'cost' => $cost,
                                'note' => $data['note'] ?? null,
                            ]);

                            $record->update([
                                'last_purchase_date' => today(),
                                'purchase_cost' => (int) round($cost / max(1, $quantity)),
                            ]);
                        });

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
                        TextInput::make('quantity')
                            ->label(__('inventory.fields.signed_quantity'))
                            ->numeric()
                            ->integer()
                            ->rules(['not_in:0'])
                            ->helperText(__('inventory.helpers.signed_quantity'))
                            ->required(),

                        Textarea::make('note')
                            ->label(__('inventory.fields.note'))
                            ->maxLength(500)
                            ->required(),
                    ])
                    ->action(function (array $data, InventoryItem $record): void {
                        DB::transaction(function () use ($data, $record) {
                            $record->movements()->create([
                                'center_id' => $record->center_id,
                                'user_id' => Auth::id(),
                                'type' => StockMovementType::Adjustment,
                                'quantity' => (int) $data['quantity'],
                                'note' => $data['note'],
                            ]);
                        });

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
