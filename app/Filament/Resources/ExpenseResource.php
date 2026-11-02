<?php

namespace App\Filament\Resources;

use App\Enums\ExpenseType;
use App\Enums\Roles;
use App\Filament\Resources\ExpenseResource\Pages\CreateExpense;
use App\Filament\Resources\ExpenseResource\Pages\EditExpense;
use App\Filament\Resources\ExpenseResource\Pages\ListExpenses;
use App\Models\Center;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\LockedMonth;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'النظام المالي';

    protected static ?int $navigationSort = 81;

    public static function getNavigationLabel(): string
    {
        return __('finance.expenses');
    }

    public static function getModelLabel(): string
    {
        return __('finance.expense');
    }

    public static function getPluralModelLabel(): string
    {
        return __('finance.expenses');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()->with(['center.city', 'user', 'shift']);

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

    public static function form(Schema $schema): Schema
    {
        $user = Auth::user();
        $isGeneralAdmin = $user->role === Roles::GeneralAdmin();

        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('type')
                            ->label(__('finance.labels.type'))
                            ->options(self::expenseTypeOptions())
                            ->required()
                            ->live(),

                        TextInput::make('payee')
                            ->label(__('finance.labels.payee'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('amount')
                            ->label(__('finance.labels.amount'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->suffix('SYP')
                            ->required(),

                        DatePicker::make('date')
                            ->label(__('finance.labels.date'))
                            ->default(today())
                            ->required()
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (LockedMonth::isLocked($value)) {
                                    $fail(__('finance.validation.month_locked'));
                                }
                            }),

                        Select::make('center_id')
                            ->label(__('finance.labels.center'))
                            ->options(fn () => Center::query()->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required($isGeneralAdmin)
                            ->visible($isGeneralAdmin)
                            ->live(),

                        Textarea::make('note')
                            ->label(__('finance.labels.note'))
                            ->rows(2)
                            ->maxLength(1000),

                        FileUpload::make('receipt_path')
                            ->label(__('finance.labels.receipt'))
                            ->directory('expense-receipts')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                            ->maxSize(4096)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make(__('finance.labels.warehouse_items'))
                    ->description(__('finance.labels.warehouse_items_hint'))
                    ->visible(fn (Get $get): bool => $get('type') === ExpenseType::WarehousePurchase->value
                        || $get('type') === ExpenseType::WarehousePurchase)
                    ->schema([
                        Repeater::make('warehouse_items')
                            ->hiddenLabel()
                            ->addActionLabel(__('finance.actions.add_item'))
                            ->defaultItems(0)
                            ->schema([
                                Select::make('inventory_item_id')
                                    ->label(__('finance.labels.item'))
                                    ->options(function (Get $get) use ($user, $isGeneralAdmin): array {
                                        $centerId = $isGeneralAdmin
                                            ? $get('../../center_id')
                                            : $user->center_id;

                                        if (! $centerId) {
                                            return [];
                                        }

                                        return InventoryItem::query()
                                            ->where('center_id', $centerId)
                                            ->where('is_active', true)
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->all();
                                    })
                                    ->searchable()
                                    ->required(),

                                TextInput::make('quantity')
                                    ->label(__('finance.labels.quantity'))
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->required(),

                                TextInput::make('cost')
                                    ->label(__('finance.labels.cost'))
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->suffix('SYP')
                                    ->required(),
                            ])
                            ->columns(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        $isGeneralAdmin = $user->role === Roles::GeneralAdmin();
        $isCityAdmin = $user->role === Roles::CityAdmin();
        $isCenterAdmin = $user->role === Roles::CenterAdmin();

        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label(__('finance.labels.date'))
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('finance.labels.type'))
                    ->badge()
                    ->formatStateUsing(fn (ExpenseType $state) => __('finance.expense_types.'.$state->value))
                    ->color(fn (ExpenseType $state): string => match ($state) {
                        ExpenseType::Operations => 'info',
                        ExpenseType::Maintenance => 'warning',
                        ExpenseType::WarehousePurchase => 'primary',
                        ExpenseType::Transport => 'gray',
                        ExpenseType::Hospitality => 'success',
                        ExpenseType::Advance => 'danger',
                        ExpenseType::Other => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('payee')
                    ->label(__('finance.labels.payee'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('amount')
                    ->label(__('finance.labels.amount'))
                    ->formatStateUsing(fn ($state) => number_format($state).' SYP')
                    ->color('danger')
                    ->sortable(),

                TextColumn::make('center.name')
                    ->label(__('finance.labels.center'))
                    ->visible($isGeneralAdmin || $isCityAdmin)
                    ->toggleable(),

                TextColumn::make('user.name')
                    ->label(__('finance.labels.user'))
                    ->toggleable(),

                IconColumn::make('receipt_path')
                    ->label(__('finance.labels.has_receipt'))
                    ->boolean()
                    ->state(fn (Expense $record) => filled($record->receipt_path)),

                TextColumn::make('shift_id')
                    ->label(__('finance.labels.shift'))
                    ->badge()
                    ->state(fn (Expense $record) => $record->shift_id
                        ? __('finance.labels.in_shift')
                        : __('finance.labels.no_shift'))
                    ->color(fn (Expense $record) => $record->shift_id ? 'info' : 'gray')
                    ->toggleable(),

                TextColumn::make('note')
                    ->label(__('finance.labels.note'))
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filters([
                SelectFilter::make('type')
                    ->label(__('finance.labels.type'))
                    ->options(self::expenseTypeOptions())
                    ->multiple()
                    ->columnSpan(1),

                SelectFilter::make('center_id')
                    ->label(__('finance.labels.center'))
                    ->options(fn () => Center::query()->pluck('name', 'id'))
                    ->searchable()
                    ->multiple()
                    ->hidden($isCenterAdmin)
                    ->columnSpan(1),

                Filter::make('date')
                    ->label(__('finance.labels.date'))
                    ->schema([
                        DatePicker::make('from')
                            ->label(__('finance.labels.from'))
                            ->columnSpan(1),
                        DatePicker::make('to')
                            ->label(__('finance.labels.to'))
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn ($q, $date) => $q->whereDate('date', '>=', $date))
                        ->when($data['to'], fn ($q, $date) => $q->whereDate('date', '<=', $date))
                    ),
            ])
            ->recordActions([
                EditAction::make(),

                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => $isGeneralAdmin),
                ])->visible(fn () => $isGeneralAdmin),
            ]);
    }

    /** @return array<string, string> */
    public static function expenseTypeOptions(): array
    {
        return collect(ExpenseType::cases())
            ->mapWithKeys(fn (ExpenseType $type) => [$type->value => __('finance.expense_types.'.$type->value)])
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenses::route('/'),
            'create' => CreateExpense::route('/create'),
            'edit' => EditExpense::route('/{record}/edit'),
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

    public static function canEdit(Model $record): bool
    {
        $role = Auth::user()->role;

        if (! in_array($role, [Roles::GeneralAdmin(), Roles::CenterAdmin()], true)) {
            return false;
        }

        return ! LockedMonth::isLocked($record->date);
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::user()->role === Roles::GeneralAdmin()
            && ! LockedMonth::isLocked($record->date);
    }
}
