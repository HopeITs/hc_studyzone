<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\EntryType;
use App\Enums\Roles;
use App\Filament\Resources\AdmissionCategoryResource\Pages\ListAdmissionCategories;
use App\Models\AdmissionPrice;
use App\Models\Center;
use App\Support\FinanceDisplay;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * "Admission categories" (daily entry, monthly subscription) and the
 * history of their prices: a default price for every center, and the
 * centers that pay a different price.
 */
class AdmissionCategoryResource extends Resource
{
    protected static ?string $model = AdmissionPrice::class;

    protected static ?string $slug = 'admission-categories';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    public static function getNavigationLabel(): string
    {
        return __('general.admission_categories.title');
    }

    public static function getModelLabel(): string
    {
        return __('general.admission_categories.price');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.admission_categories.title');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->role === Roles::GeneralAdmin();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    // History is kept as it was: a price is replaced by adding a new one.
    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return static::canViewAny() && $record->status() === 'scheduled';
    }

    public static function getEloquentQuery(): Builder
    {
        return static::canViewAny()
            ? parent::getEloquentQuery()->with('center')
            : parent::getEloquentQuery()->whereRaw('1 = 0');
    }

    /** @return array<int, string> */
    public static function typeOptions(): array
    {
        return collect(AdmissionPrice::TYPES)
            ->mapWithKeys(fn (EntryType $type): array => [$type->value => static::typeLabel($type->value)])
            ->all();
    }

    public static function typeLabel(int $type): string
    {
        return __('general.entry-types.'.$type);
    }

    public static function currency(): string
    {
        return __('general.admission_preview.'.(FinanceDisplay::enabled() ? 'old' : 'new'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('entry_type')
                ->label(__('general.admission_categories.category'))
                ->options(fn (): array => static::typeOptions())
                ->in(array_keys(static::typeOptions()))
                ->required(),
            Toggle::make('is_default')
                ->label(__('general.admission_categories.is_default'))
                ->helperText(__('general.admission_categories.is_default_help'))
                ->default(true)
                ->live(),
            Select::make('center_id')
                ->label(__('general.labels.center'))
                ->options(fn (): array => Center::query()->orderBy('name')->get()
                    ->mapWithKeys(fn (Center $center): array => [$center->id => $center->name])->all())
                ->searchable()
                ->visible(fn (Get $get): bool => ! $get('is_default'))
                ->required(fn (Get $get): bool => ! $get('is_default'))
                ->exists('centers', 'id'),
            TextInput::make('amount')
                ->label(__('general.admission_categories.amount'))
                ->suffix(fn (): string => static::currency())
                ->integer()
                ->minValue(1)
                ->maxValue(100000000)
                ->rules(fn (): array => FinanceDisplay::enabled() ? ['multiple_of:100'] : [])
                ->required(),
            DatePicker::make('starts_on')
                ->label(__('general.admission_categories.starts_on'))
                ->helperText(__('general.admission_categories.starts_on_help'))
                ->default(today())
                ->required(),
            DatePicker::make('ends_on')
                ->label(__('general.admission_categories.ends_on'))
                ->helperText(__('general.admission_categories.ends_on_help'))
                ->afterOrEqual('starts_on'),
        ]);
    }

    /** Save a price from the form; errors are shown as a notification. */
    public static function savePrice(array $data): AdmissionPrice
    {
        abort_unless(static::canCreate(), 403);

        $amount = (int) $data['amount'];

        try {
            return AdmissionPrice::schedule(
                ($data['is_default'] ?? true) ? null : (int) $data['center_id'],
                EntryType::from((int) $data['entry_type']),
                FinanceDisplay::enabled() ? intdiv($amount, 100) : $amount,
                (string) $data['starts_on'],
                $data['ends_on'] ?? null,
            );
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(__('general.admission_categories.not_saved'))
                ->body(collect($exception->errors())->flatten()->first())
                ->send();

            throw new Halt;
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('entry_type')
                    ->label(__('general.admission_categories.category'))
                    ->formatStateUsing(fn (int $state): string => static::typeLabel($state))
                    ->badge(),
                TextColumn::make('center.name')
                    ->label(__('general.admission_categories.applies_to'))
                    ->placeholder(__('general.admission_categories.all_centers'))
                    ->badge()
                    ->color(fn (AdmissionPrice $record): string => $record->isDefault() ? 'primary' : 'warning'),
                TextColumn::make('amount')
                    ->label(__('general.admission_categories.amount'))
                    ->formatStateUsing(fn (int $state): string => FinanceDisplay::format($state).' '.static::currency())
                    ->sortable(),
                TextColumn::make('starts_on')
                    ->label(__('general.admission_categories.starts_on'))
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('ends_on')
                    ->label(__('general.admission_categories.ends_on'))
                    ->date('Y-m-d')
                    ->placeholder(__('general.admission_preview.open')),
                TextColumn::make('status')
                    ->label(__('general.admission_preview.status'))
                    ->state(fn (AdmissionPrice $record): string => $record->status())
                    ->formatStateUsing(fn (string $state): string => __('general.admission_preview.'.$state))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'scheduled' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label(__('general.admission_categories.added_at'))
                    ->dateTime('Y-m-d H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('scope')
                    ->label(__('general.admission_categories.applies_to'))
                    ->options(fn (): array => ['default' => __('general.admission_categories.all_centers')]
                        + Center::query()->orderBy('name')->get()
                            ->mapWithKeys(fn (Center $center): array => [$center->id => $center->name])->all())
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        null, '' => $query,
                        'default' => $query->whereNull('center_id'),
                        default => $query->where('center_id', (int) $data['value']),
                    }),
                Filter::make('current')
                    ->label(__('general.admission_categories.current_only'))
                    ->query(fn (Builder $query): Builder => $query->covering(today()->toDateString())),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label(__('general.admission_categories.cancel_scheduled'))
                    ->using(function (AdmissionPrice $record): bool {
                        $record->cancel();

                        return true;
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdmissionCategories::route('/'),
        ];
    }
}
