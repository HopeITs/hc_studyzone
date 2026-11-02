<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\ExpenseType;
use App\Enums\Roles;
use App\Models\Center;
use App\Services\FinanceReportService;
use App\Support\FinanceDisplay;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

class FinancialReport extends Page
{
    use InteractsWithActions;
    use InteractsWithForms;
    use WithPagination;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'النظام المالي';

    protected static ?string $slug = 'financial-report';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.financial-report';

    public ?array $data = [
        'from' => null,
        'to' => null,
        'center_ids' => [],
        'movement' => 'all',
        'category' => null,
    ];

    /** Per-request caches so Blade can reference totals/rows repeatedly without re-querying. */
    protected ?array $cachedTotals = null;

    protected ?LengthAwarePaginator $cachedRows = null;

    /** @var array<int, string>|null */
    protected ?array $cachedCenterNames = null;

    public static function getNavigationLabel(): string
    {
        return __('reports.financial.title');
    }

    public function getTitle(): string
    {
        return __('reports.financial.title');
    }

    public static function canAccess(): bool
    {
        return in_array(Auth::user()?->role, [
            Roles::GeneralAdmin(),
            Roles::CityAdmin(),
            Roles::CenterAdmin(),
            Roles::Observer(),
        ], true);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill($this->defaultFilterValues());
    }

    protected function defaultFilterValues(): array
    {
        return [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
            'center_ids' => [],
            'movement' => 'all',
            'category' => null,
        ];
    }

    public function form(Schema $schema): Schema
    {
        $user = Auth::user();
        $isCenterAdmin = $user->role === Roles::CenterAdmin();
        $isCityAdmin = $user->role === Roles::CityAdmin();

        return $schema
            ->components([
                Section::make()
                    ->schema([
                        DatePicker::make('from')
                            ->label(__('reports.filters.from'))
                            ->live()
                            ->required()
                            ->columnSpan(1),

                        DatePicker::make('to')
                            ->label(__('reports.filters.to'))
                            ->live()
                            ->required()
                            ->columnSpan(1),

                        Select::make('center_ids')
                            ->label(__('reports.filters.center'))
                            ->options(fn () => $this->centerOptions())
                            ->multiple()
                            ->searchable()
                            ->placeholder($isCityAdmin
                                ? __('reports.filters.all_city_centers')
                                : __('reports.filters.all_centers'))
                            ->live()
                            ->hidden($isCenterAdmin)
                            ->default([])
                            ->columnSpan(1),

                        Select::make('movement')
                            ->label(__('reports.filters.movement'))
                            ->options([
                                'all' => __('reports.filters.movements.all'),
                                'income' => __('reports.filters.movements.income'),
                                'expenses' => __('reports.filters.movements.expenses'),
                            ])
                            ->default('all')
                            ->selectablePlaceholder(false)
                            ->live()
                            ->columnSpan(1),

                        Select::make('category')
                            ->label(__('reports.filters.category'))
                            ->options($this->categoryOptions())
                            ->placeholder(__('reports.filters.all_categories'))
                            ->searchable()
                            ->live()
                            ->columnSpan(1),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    /** Reset pagination whenever any filter changes. */
    public function updated(string $property): void
    {
        if (str_starts_with($property, 'data.')) {
            $this->resetPage();
        }
    }

    /**
     * Category options grouped as income categories + expense types.
     *
     * @return array<string, array<string, string>>
     */
    protected function categoryOptions(): array
    {
        $sales = collect(FinanceReportService::SALES_CATEGORIES)
            ->mapWithKeys(fn (string $category) => [$category => $category])
            ->all();

        $expenses = collect(ExpenseType::cases())
            ->mapWithKeys(fn (ExpenseType $type) => [$type->value => __('finance.expense_types.'.$type->value)])
            ->all();

        return [
            __('reports.filters.income_categories') => $sales,
            __('reports.filters.expense_categories') => $expenses,
        ];
    }

    /**
     * Center options the current user may pick from.
     *
     * @return Collection<int|string, mixed>
     */
    protected function centerOptions(): Collection
    {
        $user = Auth::user();

        if ($user->role === Roles::CityAdmin()) {
            return Center::query()
                ->where('city_id', $user->city_id)
                ->orderBy('order')
                ->pluck('name', 'id');
        }

        if ($user->role === Roles::CenterAdmin()) {
            return Center::query()
                ->where('id', $user->center_id)
                ->pluck('name', 'id');
        }

        // GeneralAdmin / Observer: all centers (empty selection = all).
        return Center::query()->orderBy('order')->pluck('name', 'id');
    }

    /**
     * The center ids this user is allowed to see. Null means unrestricted (all centers).
     *
     * @return array<int, int>|null
     */
    protected function allowedCenterIds(): ?array
    {
        $user = Auth::user();

        return match (true) {
            $user->role === Roles::GeneralAdmin(),
            $user->role === Roles::Observer() => null,

            $user->role === Roles::CityAdmin() => Center::query()
                ->where('city_id', $user->city_id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all(),

            $user->role === Roles::CenterAdmin() => [(int) $user->center_id],

            default => [],
        };
    }

    /**
     * Filters for FinanceReportService, with center_ids clamped server-side
     * to the user's allowed set regardless of what the form submitted.
     */
    protected function filters(): array
    {
        $state = $this->data ?? [];

        $selected = collect((array) ($state['center_ids'] ?? []))
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $allowed = $this->allowedCenterIds();

        if ($allowed === null) {
            $centerIds = $selected; // Empty = all centers.
        } else {
            $centerIds = array_values(array_intersect($selected, $allowed));

            // Empty = all of the user's allowed centers, never all centers.
            if ($centerIds === []) {
                $centerIds = $allowed;
            }
        }

        $movement = (string) ($state['movement'] ?? 'all');

        if (! in_array($movement, ['all', 'income', 'expenses'], true)) {
            $movement = 'all';
        }

        $category = $state['category'] ?? null;
        $validCategories = array_merge(FinanceReportService::SALES_CATEGORIES, ExpenseType::values());

        if (! in_array($category, $validCategories, true)) {
            $category = null;
        }

        return [
            'from' => $state['from'] ?: null,
            'to' => $state['to'] ?: null,
            'center_ids' => $centerIds,
            'movement' => $movement,
            'category' => $category,
        ];
    }

    /** @return array{income: int, expenses: int, net: int} */
    public function getTotalsProperty(): array
    {
        return $this->cachedTotals ??= app(FinanceReportService::class)->unifiedTotals($this->filters());
    }

    public function getRowsProperty(): LengthAwarePaginator
    {
        return $this->cachedRows ??= app(FinanceReportService::class)->unifiedRows($this->filters());
    }

    /**
     * Center id => name for every center referenced by the current page of rows
     * or by the active filter, resolved with a single query.
     *
     * @return array<int, string>
     */
    public function getCenterNamesProperty(): array
    {
        if ($this->cachedCenterNames !== null) {
            return $this->cachedCenterNames;
        }

        $ids = collect($this->rows->items())
            ->pluck('center_id')
            ->merge($this->filters()['center_ids'])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        return $this->cachedCenterNames = ($ids->isEmpty()
            ? []
            : Center::query()->whereIn('id', $ids)->pluck('name', 'id')->all());
    }

    /** Human label of the centers covered by the active filter, for the print header. */
    public function filteredCentersLabel(): string
    {
        $user = Auth::user();
        $selectionEmpty = collect((array) ($this->data['center_ids'] ?? []))->filter()->isEmpty();

        if ($selectionEmpty && $user->role !== Roles::CenterAdmin()) {
            return $user->role === Roles::CityAdmin()
                ? __('reports.filters.all_city_centers')
                : __('reports.filters.all_centers');
        }

        $names = collect($this->filters()['center_ids'])
            ->map(fn (int $id) => $this->centerNames[$id] ?? null)
            ->filter()
            ->values();

        return $names->isEmpty() ? __('reports.filters.all_centers') : $names->implode('، ');
    }

    public function oldCurrencyEnabled(): bool
    {
        return FinanceDisplay::enabled();
    }

    public function formatMoney(int $amount): string
    {
        return FinanceDisplay::format($amount);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleOldCurrency')
                ->label(__('reports.actions.old_currency'))
                ->icon('heroicon-o-arrows-right-left')
                ->color(fn (): string => FinanceDisplay::enabled() ? 'warning' : 'gray')
                ->badge(fn (): ?string => FinanceDisplay::enabled() ? __('reports.old_currency.on') : null)
                ->action(function (): void {
                    FinanceDisplay::toggle();
                }),

            Action::make('print')
                ->label(__('reports.actions.print'))
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->action(fn () => $this->dispatch('financial-report-print')),
        ];
    }
}
