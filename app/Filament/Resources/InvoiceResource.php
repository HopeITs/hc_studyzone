<?php

namespace App\Filament\Resources;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\Roles;
use App\Filament\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Filament\Resources\InvoiceResource\Pages\EditInvoice;
use App\Filament\Resources\InvoiceResource\Pages\ListInvoices;
use App\Filament\Resources\InvoiceResource\Pages\ViewInvoice;
use App\Models\Center;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\LockedMonth;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Student;
use App\Support\FinanceAccess;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static string|\UnitEnum|null $navigationGroup = 'النظام المالي';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('invoices.labels.invoices');
    }

    public static function getModelLabel(): string
    {
        return __('invoices.labels.invoice');
    }

    public static function getPluralModelLabel(): string
    {
        return __('invoices.labels.invoices');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()
            ->with(['center.city', 'user', 'student'])
            ->withCount('items');

        return match (true) {
            $user->role === Roles::GeneralAdmin(),
            $user->role === Roles::Observer() => $query,

            $user->role === Roles::CityAdmin() => $query->whereHas(
                'center',
                fn ($q) => $q->where('city_id', $user->city_id)
            ),

            $user->role === Roles::CenterAdmin(),
            $user->role === Roles::Cashier() => $query->where('center_id', $user->center_id),

            default => $query->whereRaw('1 = 0'),
        };
    }

    public static function form(Schema $schema): Schema
    {
        $user = Auth::user();
        $isGeneralAdmin = $user->role === Roles::GeneralAdmin();

        return $schema
            ->components([
                Section::make(__('invoices.sections.details'))
                    ->schema([
                        $isGeneralAdmin
                            ? Select::make('center_id')
                                ->label(__('invoices.fields.center'))
                                ->options(fn () => Center::where('is_active', true)->orderBy('order')->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                // Lines price per center: changing the center resets them.
                                ->afterStateUpdated(fn (Set $set) => $set('items', []))
                                ->disabledOn('edit')
                                ->dehydratedWhenHidden()
                            : Hidden::make('center_id')
                                ->default($user->center_id)
                                ->dehydrated(),

                        Select::make('student_id')
                            ->label(__('invoices.fields.student'))
                            ->searchable()
                            // Search server-side: students are too many to preload.
                            ->getSearchResultsUsing(fn (string $search): array => Student::query()
                                ->where(function ($query) use ($search): void {
                                    $query->where('firstname', 'like', "%{$search}%")
                                        ->orWhere('lastname', 'like', "%{$search}%")
                                        ->orWhere('father_name', 'like', "%{$search}%")
                                        ->orWhere('phone', 'like', "%{$search}%");
                                })
                                ->limit(20)
                                ->get()
                                ->mapWithKeys(fn (Student $student) => [
                                    $student->id => $student->getFilamentName(),
                                ])
                                ->all())
                            ->getOptionLabelUsing(fn ($value): ?string => Student::find($value)?->getFilamentName())
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if ($state && ($student = Student::find($state))) {
                                    $set('beneficiary_name', $student->getFilamentName());
                                }
                            }),

                        TextInput::make('beneficiary_name')
                            ->label(__('invoices.fields.beneficiary_name'))
                            ->required()
                            ->disabled(fn (Get $get): bool => filled($get('student_id')))
                            ->dehydrated()
                            ->maxLength(255),

                        TextInput::make('student_number')
                            ->label(__('invoices.fields.student_number'))
                            ->maxLength(255),

                        Select::make('payment_method')
                            ->label(__('invoices.fields.payment_method'))
                            ->options([
                                PaymentMethod::Cash->value => __('invoices.payment_methods.cash'),
                            ])
                            ->default(PaymentMethod::Cash->value)
                            ->selectablePlaceholder(false)
                            ->required(),

                        TextInput::make('discount')
                            ->label(__('invoices.fields.discount'))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->prefix('SYP')
                            ->live(onBlur: true),

                        Textarea::make('note')
                            ->label(__('invoices.fields.note'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make(__('invoices.sections.items'))
                    ->schema([
                        Repeater::make('items')
                            ->label(__('invoices.fields.items'))
                            ->addActionLabel(__('invoices.actions.add_line'))
                            ->minItems(1)
                            ->required()
                            ->defaultItems(1)
                            ->live()
                            ->schema([
                                Select::make('kind')
                                    ->label(__('invoices.fields.kind'))
                                    ->options([
                                        'inventory' => __('invoices.kinds.inventory'),
                                        'service' => __('invoices.kinds.service'),
                                    ])
                                    ->default('inventory')
                                    ->selectablePlaceholder(false)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('inventory_item_id', null);
                                        $set('service_id', null);
                                        $set('unit_price', null);
                                    }),

                                Select::make('inventory_item_id')
                                    ->label(__('invoices.fields.inventory_item'))
                                    ->options(fn (Get $get): array => self::inventoryOptionsFor($get('../../center_id')))
                                    ->searchable()
                                    ->visible(fn (Get $get): bool => $get('kind') === 'inventory')
                                    ->required(fn (Get $get): bool => $get('kind') === 'inventory')
                                    ->live()
                                    ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                        $set('unit_price', self::unitPriceFor('inventory', $state, $get('../../center_id')));
                                    }),

                                Select::make('service_id')
                                    ->label(__('invoices.fields.service'))
                                    ->options(fn (Get $get): array => self::serviceOptionsFor($get('../../center_id')))
                                    ->searchable()
                                    ->visible(fn (Get $get): bool => $get('kind') === 'service')
                                    ->required(fn (Get $get): bool => $get('kind') === 'service')
                                    ->live()
                                    ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                        $set('unit_price', self::unitPriceFor('service', $state, $get('../../center_id')));
                                    }),

                                TextInput::make('quantity')
                                    ->label(__('invoices.fields.quantity'))
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true),

                                TextInput::make('unit_price')
                                    ->label(__('invoices.fields.unit_price'))
                                    ->numeric()
                                    ->prefix('SYP')
                                    ->disabled()
                                    ->dehydrated(),

                                Placeholder::make('line_total')
                                    ->label(__('invoices.fields.line_total'))
                                    ->content(fn (Get $get): string => number_format(
                                        max(0, (int) $get('quantity')) * max(0, (int) $get('unit_price'))
                                    ).' SYP'),
                            ])
                            ->columns(5)
                            ->columnSpanFull(),

                        Placeholder::make('total_preview')
                            ->label(__('invoices.fields.total'))
                            ->content(function (Get $get): HtmlString {
                                $sum = 0;

                                foreach ((array) $get('items') as $row) {
                                    $sum += max(0, (int) ($row['quantity'] ?? 0)) * max(0, (int) ($row['unit_price'] ?? 0));
                                }

                                $total = max(0, $sum - max(0, (int) $get('discount')));

                                return new HtmlString(
                                    '<span class="text-lg font-bold">'.number_format($total).' SYP</span>'
                                    .' <span class="text-sm text-gray-400">'
                                    .e(__('invoices.labels.old_currency_hint', ['amount' => number_format($total * 100)]))
                                    .'</span>'
                                );
                            }),
                    ]),
            ])->columns(1);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('invoices.sections.details'))
                    ->schema([
                        TextEntry::make('number')
                            ->label(__('invoices.fields.number')),

                        TextEntry::make('status')
                            ->label(__('invoices.fields.status'))
                            ->badge()
                            ->formatStateUsing(fn (InvoiceStatus $state): string => __('invoices.status.'.$state->value))
                            ->color(fn (InvoiceStatus $state): string => match ($state) {
                                InvoiceStatus::Paid => 'success',
                                InvoiceStatus::Unpaid => 'warning',
                                InvoiceStatus::Cancelled => 'danger',
                            }),

                        TextEntry::make('beneficiary_name')
                            ->label(__('invoices.fields.beneficiary_name')),

                        TextEntry::make('student.id')
                            ->label(__('invoices.fields.student'))
                            ->state(fn (Invoice $record): ?string => $record->student?->getFilamentName())
                            ->placeholder('-'),

                        TextEntry::make('student_number')
                            ->label(__('invoices.fields.student_number'))
                            ->placeholder('-'),

                        TextEntry::make('center.name')
                            ->label(__('invoices.fields.center')),

                        TextEntry::make('user.name')
                            ->label(__('invoices.fields.user')),

                        TextEntry::make('payment_method')
                            ->label(__('invoices.fields.payment_method'))
                            ->formatStateUsing(fn (PaymentMethod $state): string => __('invoices.payment_methods.'.$state->value)),

                        TextEntry::make('created_at')
                            ->label(__('invoices.fields.created_at'))
                            ->dateTime('Y-m-d H:i'),

                        TextEntry::make('discount')
                            ->label(__('invoices.fields.discount'))
                            ->formatStateUsing(fn ($state): string => number_format((int) $state).' SYP'),

                        TextEntry::make('total')
                            ->label(__('invoices.fields.total'))
                            ->formatStateUsing(fn ($state, Invoice $record): string => number_format((int) $state).' SYP ('
                                .__('invoices.labels.old_currency_hint', ['amount' => number_format((int) $state * 100)]).')'),

                        TextEntry::make('note')
                            ->label(__('invoices.fields.note'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make(__('invoices.sections.items'))
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label(__('invoices.fields.items'))
                            ->schema([
                                TextEntry::make('item_name')
                                    ->label(__('invoices.fields.item'))
                                    ->state(fn ($record): string => $record->inventoryItem?->name
                                        ?? $record->service?->name
                                        ?? '-'),

                                TextEntry::make('quantity')
                                    ->label(__('invoices.fields.quantity')),

                                TextEntry::make('unit_price')
                                    ->label(__('invoices.fields.unit_price'))
                                    ->formatStateUsing(fn ($state): string => number_format((int) $state).' SYP'),

                                TextEntry::make('line_total')
                                    ->label(__('invoices.fields.line_total'))
                                    ->formatStateUsing(fn ($state): string => number_format((int) $state).' SYP'),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                    ]),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        $isGeneralAdmin = $user->role === Roles::GeneralAdmin();
        $isCenterAdmin = $user->role === Roles::CenterAdmin();
        // Cashiers collect too — but never cancel (that stays GeneralAdmin-only).
        $canCollect = $isGeneralAdmin || $isCenterAdmin || $user->role === Roles::Cashier();

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label(__('invoices.fields.number'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('beneficiary_name')
                    ->label(__('invoices.fields.beneficiary_name'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('status')
                    ->label(__('invoices.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (InvoiceStatus $state): string => __('invoices.status.'.$state->value))
                    ->color(fn (InvoiceStatus $state): string => match ($state) {
                        InvoiceStatus::Paid => 'success',
                        InvoiceStatus::Unpaid => 'warning',
                        InvoiceStatus::Cancelled => 'danger',
                    })
                    ->sortable(),

                TextColumn::make('total')
                    ->label(__('invoices.fields.total'))
                    ->formatStateUsing(fn ($state): string => number_format((int) $state).' SYP')
                    ->color('success')
                    ->sortable(),

                TextColumn::make('discount')
                    ->label(__('invoices.fields.discount'))
                    ->formatStateUsing(fn ($state): string => number_format((int) $state).' SYP')
                    ->toggleable(),

                TextColumn::make('items_count')
                    ->label(__('invoices.fields.items_count'))
                    ->badge()
                    ->color('info'),

                TextColumn::make('center.name')
                    ->label(__('invoices.fields.center'))
                    ->sortable()
                    ->visible($isGeneralAdmin || $user->role === Roles::CityAdmin()),

                TextColumn::make('user.name')
                    ->label(__('invoices.fields.user'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('invoices.fields.created_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filters([
                SelectFilter::make('status')
                    ->label(__('invoices.fields.status'))
                    ->options([
                        InvoiceStatus::Paid->value => __('invoices.status.paid'),
                        InvoiceStatus::Unpaid->value => __('invoices.status.unpaid'),
                        InvoiceStatus::Cancelled->value => __('invoices.status.cancelled'),
                    ])
                    ->multiple(),

                SelectFilter::make('center_id')
                    ->label(__('invoices.fields.center'))
                    ->options(fn () => Center::orderBy('order')->pluck('name', 'id'))
                    ->multiple()
                    ->hidden($isCenterAdmin),

                Filter::make('created_at')
                    ->label(__('invoices.fields.created_at'))
                    ->schema([
                        DatePicker::make('from')
                            ->label(__('invoices.fields.date_from')),
                        DatePicker::make('to')
                            ->label(__('invoices.fields.date_to')),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['to'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label(__('invoices.actions.view')),

                Action::make('collect')
                    ->label(__('invoices.actions.collect'))
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(__('invoices.messages.collect_confirm'))
                    ->visible(fn (Invoice $record): bool => $canCollect
                        && $record->status === InvoiceStatus::Unpaid
                        && ! LockedMonth::isLocked(now())
                        && ! LockedMonth::isLocked($record->created_at))
                    ->action(function (Invoice $record): void {
                        try {
                            $record->markPaid();

                            Notification::make()
                                ->success()
                                ->title(__('invoices.messages.collected'))
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title(__('invoices.messages.collect_failed'))
                                ->body(collect($e->errors())->flatten()->first())
                                ->send();
                        }
                    }),

                Action::make('cancel')
                    ->label(__('invoices.actions.cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('invoices.messages.cancel_confirm'))
                    ->visible(fn (Invoice $record): bool => $isGeneralAdmin
                        && $record->status !== InvoiceStatus::Cancelled
                        && ! LockedMonth::isLocked(now())
                        && ! LockedMonth::isLocked($record->created_at))
                    ->action(function (Invoice $record): void {
                        try {
                            $record->markCancelled();

                            Notification::make()
                                ->success()
                                ->title(__('invoices.messages.cancelled'))
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title(__('invoices.messages.cancel_failed'))
                                ->body(collect($e->errors())->flatten()->first())
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->label(__('invoices.actions.edit'))
                    ->visible(fn (Invoice $record): bool => static::canEdit($record)),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'view' => ViewInvoice::route('/{record}'),
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return FinanceAccess::allowed() && in_array(Auth::user()->role, [
            Roles::GeneralAdmin(),
            Roles::CityAdmin(),
            Roles::CenterAdmin(),
            Roles::Observer(),
            Roles::Cashier(),
        ], true);
    }

    public static function canCreate(): bool
    {
        return FinanceAccess::allowed() && in_array(Auth::user()->role, [
            Roles::GeneralAdmin(),
            Roles::CenterAdmin(),
        ], true) && ! LockedMonth::isLocked(now());
    }

    public static function canEdit(Model $record): bool
    {
        $user = Auth::user();

        if (! FinanceAccess::allowed($user)
            || ! in_array($user->role, [Roles::GeneralAdmin(), Roles::CenterAdmin()], true)) {
            return false;
        }

        return $record->status === InvoiceStatus::Unpaid
            && ! LockedMonth::isLocked($record->created_at);
    }

    public static function canDelete(Model $record): bool
    {
        return false; // Cancellation is the only path — invoices are never deleted.
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * Active buffet items of a center, labelled "name (stock)".
     *
     * @return array<int, string>
     */
    public static function inventoryOptionsFor(int|string|null $centerId): array
    {
        if (! $centerId) {
            return [];
        }

        return InventoryItem::query()
            ->where('center_id', (int) $centerId)
            ->where('is_active', true)
            ->withSum('movements as stock_sum', 'quantity')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (InventoryItem $item): array => [
                $item->id => "{$item->name} ({$item->stock_sum}".' '.__('invoices.labels.in_stock').')',
            ])
            ->all();
    }

    /**
     * Services having an active price in a center.
     *
     * @return array<int, string>
     */
    public static function serviceOptionsFor(int|string|null $centerId): array
    {
        if (! $centerId) {
            return [];
        }

        return Service::query()
            ->where('is_active', true)
            ->whereHas('centerPrices', fn ($q) => $q
                ->where('center_id', (int) $centerId)
                ->where('is_active', true)
                ->whereNotNull('price'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** Snapshot unit price for one line, or null when it cannot be resolved. */
    public static function unitPriceFor(string $kind, int|string|null $id, int|string|null $centerId): ?int
    {
        if (! $id || ! $centerId) {
            return null;
        }

        if ($kind === 'inventory') {
            $item = InventoryItem::query()
                ->where('center_id', (int) $centerId)
                ->where('is_active', true)
                ->find((int) $id);

            return $item ? (int) $item->sale_price : null;
        }

        return ServicePrice::activePricesFor((int) $centerId, [(int) $id])[(int) $id] ?? null;
    }

    /**
     * Build invoice line payloads from submitted repeater rows, re-resolving
     * every price server-side. Never trusts client-side unit prices.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     *
     * @throws ValidationException
     */
    public static function buildLines(int $centerId, array $rows): array
    {
        if (empty($rows)) {
            throw ValidationException::withMessages([
                'items' => __('invoices.errors.no_lines'),
            ]);
        }

        $serviceIds = collect($rows)
            ->filter(fn (array $row): bool => ($row['kind'] ?? null) === 'service')
            ->pluck('service_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->all();

        $servicePrices = ServicePrice::activePricesFor($centerId, $serviceIds);

        $lines = [];

        foreach ($rows as $row) {
            $kind = $row['kind'] ?? null;
            $quantity = (int) ($row['quantity'] ?? 0);

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    'items' => __('invoices.errors.invalid_line'),
                ]);
            }

            if ($kind === 'inventory' && ! empty($row['inventory_item_id'])) {
                $item = InventoryItem::query()
                    ->where('center_id', $centerId)
                    ->where('is_active', true)
                    ->find((int) $row['inventory_item_id']);

                if (! $item) {
                    throw ValidationException::withMessages([
                        'items' => __('invoices.errors.item_not_in_center'),
                    ]);
                }

                $unitPrice = (int) $item->sale_price;
                $inventoryItemId = $item->id;
                $serviceId = null;
            } elseif ($kind === 'service' && ! empty($row['service_id'])) {
                $serviceId = (int) $row['service_id'];
                $unitPrice = $servicePrices[$serviceId] ?? null;

                if ($unitPrice === null) {
                    throw ValidationException::withMessages([
                        'items' => __('invoices.errors.no_active_price'),
                    ]);
                }

                $inventoryItemId = null;
            } else {
                throw ValidationException::withMessages([
                    'items' => __('invoices.errors.invalid_line'),
                ]);
            }

            $lines[] = [
                'inventory_item_id' => $inventoryItemId,
                'service_id' => $serviceId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $quantity * $unitPrice,
            ];
        }

        return $lines;
    }
}
