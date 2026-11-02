<?php

namespace App\Filament\Resources;

use App\Enums\CashShiftStatus;
use App\Enums\InvoiceStatus;
use App\Enums\Roles;
use App\Filament\Resources\CashShiftResource\Pages\ListCashShifts;
use App\Filament\Resources\CashShiftResource\Pages\ViewCashShift;
use App\Models\CashShift;
use App\Models\Center;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CashShiftResource extends Resource
{
    protected static ?string $model = CashShift::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'النظام المالي';

    protected static ?int $navigationSort = 82;

    public static function getNavigationLabel(): string
    {
        return __('finance.cash_shifts');
    }

    public static function getModelLabel(): string
    {
        return __('finance.cash_shift');
    }

    public static function getPluralModelLabel(): string
    {
        return __('finance.cash_shifts');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()
            ->with(['center.city', 'user'])
            ->withCount([
                'invoices as invoices_count' => fn ($q) => $q->where('status', InvoiceStatus::Paid->value),
            ]);

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

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('finance.labels.shift_details'))
                    ->schema([
                        TextEntry::make('center.name')
                            ->label(__('finance.labels.center')),

                        TextEntry::make('user.name')
                            ->label(__('finance.labels.user')),

                        TextEntry::make('status')
                            ->label(__('finance.labels.status'))
                            ->badge()
                            ->formatStateUsing(fn (CashShiftStatus $state) => __('finance.shift_status.'.$state->value))
                            ->color(fn (CashShiftStatus $state): string => match ($state) {
                                CashShiftStatus::Open => 'success',
                                CashShiftStatus::Closed => 'gray',
                            }),

                        TextEntry::make('opened_at')
                            ->label(__('finance.labels.opened_at'))
                            ->dateTime('Y-m-d H:i'),

                        TextEntry::make('closed_at')
                            ->label(__('finance.labels.closed_at'))
                            ->dateTime('Y-m-d H:i')
                            ->placeholder('-'),
                    ])->columns(3),

                Section::make(fn (CashShift $record) => $record->isOpen()
                    ? __('finance.labels.live_values')
                    : __('finance.labels.snapshot_values'))
                    ->schema([
                        TextEntry::make('opening_cash')
                            ->label(__('finance.labels.opening_cash'))
                            ->formatStateUsing(fn ($state) => number_format($state).' SYP'),

                        TextEntry::make('cash_income')
                            ->label(__('finance.labels.cash_income'))
                            ->state(fn (CashShift $record) => $record->isOpen()
                                ? $record->computed_cash_income
                                : $record->cash_income)
                            ->formatStateUsing(fn ($state) => number_format($state).' SYP')
                            ->color('success'),

                        TextEntry::make('expenses_total')
                            ->label(__('finance.labels.expenses_total'))
                            ->state(fn (CashShift $record) => $record->isOpen()
                                ? $record->computed_expenses
                                : $record->expenses_total)
                            ->formatStateUsing(fn ($state) => number_format($state).' SYP')
                            ->color('danger'),

                        TextEntry::make('invoices_count')
                            ->label(__('finance.labels.invoices_count'))
                            ->state(fn (CashShift $record): int => $record->invoices()
                                ->where('status', InvoiceStatus::Paid->value)
                                ->count()),

                        TextEntry::make('expected_cash')
                            ->label(__('finance.labels.expected_cash'))
                            ->state(fn (CashShift $record) => $record->isOpen()
                                ? $record->expected_cash
                                : $record->opening_cash + $record->cash_income - $record->expenses_total)
                            ->formatStateUsing(fn ($state) => number_format($state).' SYP')
                            ->weight('bold'),

                        TextEntry::make('counted_cash')
                            ->label(__('finance.labels.counted_cash'))
                            ->formatStateUsing(fn ($state) => number_format($state).' SYP')
                            ->placeholder('-')
                            ->visible(fn (CashShift $record) => ! $record->isOpen()),

                        TextEntry::make('difference')
                            ->label(__('finance.labels.difference'))
                            ->badge()
                            ->formatStateUsing(fn ($state) => number_format($state).' SYP')
                            ->color(fn (int $state): string => match (true) {
                                $state === 0 => 'success',
                                $state < 0 => 'danger',
                                default => 'warning',
                            })
                            ->placeholder('-')
                            ->visible(fn (CashShift $record) => ! $record->isOpen()),

                        TextEntry::make('closing_note')
                            ->label(__('finance.labels.closing_note'))
                            ->placeholder('-')
                            ->columnSpanFull()
                            ->visible(fn (CashShift $record) => ! $record->isOpen()),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        $isGeneralAdmin = $user->role === Roles::GeneralAdmin();
        $isCityAdmin = $user->role === Roles::CityAdmin();
        $isCenterAdmin = $user->role === Roles::CenterAdmin();

        return $table
            ->defaultSort('opened_at', 'desc')
            ->columns([
                TextColumn::make('center.name')
                    ->label(__('finance.labels.center'))
                    ->visible($isGeneralAdmin || $isCityAdmin),

                TextColumn::make('user.name')
                    ->label(__('finance.labels.user'))
                    ->toggleable(),

                TextColumn::make('opened_at')
                    ->label(__('finance.labels.opened_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('closed_at')
                    ->label(__('finance.labels.closed_at'))
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('opening_cash')
                    ->label(__('finance.labels.opening_cash'))
                    ->formatStateUsing(fn ($state) => number_format($state).' SYP'),

                TextColumn::make('cash_income')
                    ->label(__('finance.labels.cash_income'))
                    ->formatStateUsing(fn ($state) => number_format($state).' SYP')
                    ->placeholder('-')
                    ->color('success'),

                TextColumn::make('expenses_total')
                    ->label(__('finance.labels.expenses_total'))
                    ->formatStateUsing(fn ($state) => number_format($state).' SYP')
                    ->placeholder('-')
                    ->color('danger'),

                TextColumn::make('invoices_count')
                    ->label(__('finance.labels.invoices_count'))
                    ->toggleable(),

                TextColumn::make('counted_cash')
                    ->label(__('finance.labels.counted_cash'))
                    ->formatStateUsing(fn ($state) => number_format($state).' SYP')
                    ->placeholder('-'),

                TextColumn::make('difference')
                    ->label(__('finance.labels.difference'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => number_format($state).' SYP')
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'success',
                        $state < 0 => 'danger',
                        default => 'warning',
                    })
                    ->placeholder('-'),

                TextColumn::make('status')
                    ->label(__('finance.labels.status'))
                    ->badge()
                    ->formatStateUsing(fn (CashShiftStatus $state) => __('finance.shift_status.'.$state->value))
                    ->color(fn (CashShiftStatus $state): string => match ($state) {
                        CashShiftStatus::Open => 'success',
                        CashShiftStatus::Closed => 'gray',
                    })
                    ->sortable(),
            ])
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filters([
                SelectFilter::make('status')
                    ->label(__('finance.labels.status'))
                    ->options([
                        CashShiftStatus::Open->value => __('finance.shift_status.open'),
                        CashShiftStatus::Closed->value => __('finance.shift_status.closed'),
                    ]),

                SelectFilter::make('center_id')
                    ->label(__('finance.labels.center'))
                    ->options(fn () => Center::query()->pluck('name', 'id'))
                    ->searchable()
                    ->multiple()
                    ->hidden($isCenterAdmin),

                Filter::make('opened_at')
                    ->label(__('finance.labels.opened_at'))
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
                        ->when($data['from'], fn ($q, $date) => $q->whereDate('opened_at', '>=', $date))
                        ->when($data['to'], fn ($q, $date) => $q->whereDate('opened_at', '<=', $date))
                    ),
            ])
            ->recordActions([
                ViewAction::make(),

                self::closeShiftAction(),

                self::printReportAction(),
            ]);
    }

    /** Shared "close shift" action used on the table and the view page. */
    public static function closeShiftAction(): Action
    {
        return Action::make('close_shift')
            ->label(__('finance.actions.close_shift'))
            ->icon('heroicon-o-lock-closed')
            ->color('danger')
            ->visible(fn (CashShift $record) => $record->isOpen() && self::userManagesShifts())
            ->fillForm(fn (CashShift $record) => [
                'computed_cash_income' => number_format($record->computed_cash_income).' SYP',
                'computed_expenses' => number_format($record->computed_expenses).' SYP',
                'expected_cash' => number_format($record->expected_cash).' SYP',
            ])
            ->schema([
                TextInput::make('computed_cash_income')
                    ->label(__('finance.labels.cash_income'))
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('computed_expenses')
                    ->label(__('finance.labels.expenses_total'))
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('expected_cash')
                    ->label(__('finance.labels.expected_cash'))
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('counted_cash')
                    ->label(__('finance.labels.counted_cash'))
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->suffix('SYP')
                    ->live()
                    ->required(),

                TextEntry::make('difference_preview')
                    ->label(__('finance.labels.difference'))
                    ->state(fn (Get $get, CashShift $record): string => number_format((int) $get('counted_cash') - $record->expected_cash).' SYP')
                    ->color(fn (Get $get, CashShift $record): string => match (true) {
                        (int) $get('counted_cash') - $record->expected_cash === 0 => 'success',
                        (int) $get('counted_cash') - $record->expected_cash < 0 => 'danger',
                        default => 'warning',
                    }),

                Textarea::make('closing_note')
                    ->label(__('finance.labels.closing_note'))
                    ->rows(2)
                    ->maxLength(1000)
                    ->required(fn (Get $get, CashShift $record): bool => (int) $get('counted_cash') !== $record->expected_cash)
                    ->helperText(__('finance.validation.note_required_on_difference')),
            ])
            ->requiresConfirmation()
            ->modalHeading(__('finance.actions.close_shift'))
            ->modalDescription(__('finance.messages.close_shift_confirm'))
            ->action(function (CashShift $record, array $data): void {
                // Server-side re-checks: role + shift still open.
                if (! self::userManagesShifts()) {
                    Notification::make()
                        ->title(__('finance.messages.not_allowed'))
                        ->danger()
                        ->send();

                    return;
                }

                if (! $record->isOpen()) {
                    Notification::make()
                        ->title(__('finance.messages.shift_already_closed'))
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $record->close((int) $data['counted_cash'], $data['closing_note'] ?? null);
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title(collect($e->errors())->flatten()->first())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('finance.messages.shift_closed'))
                    ->success()
                    ->send();
            });
    }

    /** Shared "print close report" action used on the table and the view page. */
    public static function printReportAction(): Action
    {
        return Action::make('print')
            ->label(__('finance.actions.print_report'))
            ->icon('heroicon-o-printer')
            ->url(fn (CashShift $record): string => route('cash-shifts.print', $record))
            ->openUrlInNewTab()
            ->visible(fn (CashShift $record): bool => ! $record->isOpen());
    }

    /** GeneralAdmin and CenterAdmin may open/close shifts. */
    public static function userManagesShifts(): bool
    {
        return in_array(Auth::user()->role, [
            Roles::GeneralAdmin(),
            Roles::CenterAdmin(),
        ], true);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCashShifts::route('/'),
            'view' => ViewCashShift::route('/{record}'),
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
        // Shifts are opened only via the list page's "open shift" header action.
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        // Shifts are never edited directly — only opened/closed via actions.
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
