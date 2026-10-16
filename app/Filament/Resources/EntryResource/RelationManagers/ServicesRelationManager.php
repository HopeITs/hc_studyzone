<?php

namespace App\Filament\Resources\EntryResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Schemas\Components\Grid;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Helpers\ServiceHelper;
use App\Models\EntryService;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'services';

    protected static ?string $recordTitleAttribute = 'service.name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Keep minimal — the real form is in the custom header action
            ]);
    }

    // ─── NEW: Sync Method ───────────────────────────────────────────
    protected function syncEntryReferenceColumns(): void
    {
        $entry = $this->getOwnerRecord();

        // 1. Get all service IDs currently attached to this entry
        $currentServiceIds = $entry->services()->pluck('service_id')->toArray();

        // 2. Fetch all possible reference columns to reset them safely to false
        $allPossibleColumns = Service::whereNotNull('reference_column_name')
            ->pluck('reference_column_name')
            ->unique()
            ->filter()
            ->toArray();

        // Initialize all to false (handles deletions automatically)
        $entryUpdates = array_fill_keys($allPossibleColumns, false);

        // 3. Fetch the reference_column_name(s) for the currently attached services
        if (!empty($currentServiceIds)) {
            $activeReferenceColumns = Service::whereIn('id', $currentServiceIds)
                ->whereNotNull('reference_column_name')
                ->pluck('reference_column_name')
                ->unique()
                ->filter()
                ->toArray();

            // Override active columns to true
            foreach ($activeReferenceColumns as $column) {
                $entryUpdates[$column] = true;
            }
        }

        // 4. Update the StudentEntry record
        if (!empty($entryUpdates)) {
            $entry->update($entryUpdates);
        }
    }
    // ─── END NEW ────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('service.name')
            ->columns([
                TextColumn::make('service.name')
                    ->label(__('general.labels.service'))
                    ->searchable(),
                TextColumn::make('service.category.name')
                    ->label(__('general.labels.category'))
                    ->searchable(),
                TextColumn::make('amount')
                    ->formatStateUsing(fn (Model $record) => $record->amount.' '.__('general.services_module.units.'.$record->service->category->unit))
                    ->label(__('general.labels.quantity'))
                    ->sortable(),
                TextColumn::make('price_at_time')
                    ->label(__('general.labels.unit_price'))
                    ->money('SYP')
                    ->sortable(),
                TextColumn::make('total')
                    ->label(__('general.labels.total_price'))
                    ->money('SYP')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->formatStateUsing(fn (Model $record) => $record->total * 100)
                    ->label(__('general.labels.old_currency_price'))
                    ->money('SYP')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('general.labels.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([
                Action::make('addServices')
                    ->label(__('general.labels.add_services'))
                    ->icon('heroicon-o-plus')
                    ->modalHeading(__('general.labels.buy_additional_services'))
                    ->modalSubmitActionLabel(__('general.labels.add_update_total'))
                    ->schema(function () {
                        $record = $this->getOwnerRecord();
                        $centerId = $record->center_id;

                        // Existing daily service IDs already linked to this entry
                        $existingDailyServiceIds = $record->services()
                            ->whereHas('service.category', fn ($q) => $q->where('unit', 'day'))
                            ->pluck('service_id')
                            ->toArray();

                        return [
                            CheckboxList::make('day_services')
                                ->label(__('general.labels.one_time_daily_services'))
                                ->options(function () use ($centerId, $existingDailyServiceIds) {
                                    if (! $centerId) {
                                        return [];
                                    }

                                    return Service::where('is_active', 1)
                                        ->whereHas('category', fn ($q) => $q->where('unit', 'day'))
                                        ->whereHas('centerPrices', fn ($q) => $q->where('center_id', $centerId)->where('is_active', 1))
                                        ->whereNotIn('id', $existingDailyServiceIds)
                                        ->with(['category', 'centerPrices' => fn ($q) => $q->where('center_id', $centerId)->where('is_active', 1)])
                                        ->withCount('entryServices as usage_count')
                                        ->orderByDesc('usage_count')
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(function ($service) {
                                            $price = $service->centerPrices->first()?->price ?? 0;
                                            $categoryName = $service->category?->name ?? '';

                                            return [$service->id => "{$categoryName}: {$service->name} — ".number_format($price).' ل.س/يوم'];
                                        });
                                })
                                ->searchable()
                                ->columns(2)
                                ->bulkToggleable()
                                ->columnSpanFull(),

                            Repeater::make('services')
                                ->label(__('general.labels.other_services'))
                                ->schema([
                                    Grid::make()
                                        ->schema([
                                            Select::make('category_id')
                                                ->label(__('general.labels.category'))
                                                ->options(function () use ($centerId) {
                                                    if (! $centerId) {
                                                        return [];
                                                    }

                                                    return ServiceCategory::where(function ($q) {
                                                        $q->whereNull('unit')->orWhere('unit', '!=', 'day');
                                                    })
                                                        ->whereHas('services', fn ($q) => $q->where('is_active', 1)->whereHas('centerPrices', fn ($pq) => $pq->where('center_id', $centerId)->where('is_active', 1)))
                                                        ->orderBy('name')
                                                        ->pluck('name', 'id');
                                                })
                                                ->required()
                                                ->live()
                                                ->searchable()
                                                ->columnSpan(2)
                                                ->dehydrated(false)
                                                ->afterStateUpdated(fn (callable $set) => $set('service_id', null)),

                                            Select::make('service_id')
                                                ->label(__('general.labels.service'))
                                                ->options(function (callable $get) use ($centerId) {
                                                    $categoryId = $get('category_id');
                                                    if (! $categoryId || ! $centerId) {
                                                        return [];
                                                    }

                                                    return Service::where('category_id', $categoryId)
                                                        ->where('is_active', 1)
                                                        ->whereHas('centerPrices', fn ($q) => $q->where('center_id', $centerId)->where('is_active', 1))
                                                        ->with(['centerPrices' => fn ($q) => $q->where('center_id', $centerId)->where('is_active', 1)])
                                                        ->withCount('entryServices as usage_count')
                                                        ->orderByDesc('usage_count')
                                                        ->orderBy('name')
                                                        ->get()
                                                        ->mapWithKeys(function ($service) {
                                                            $price = $service->centerPrices->first()?->price ?? 0;

                                                            return [$service->id => "{$service->name} — ".number_format($price).' ل.س'];
                                                        });
                                                })
                                                ->required()
                                                ->live()
                                                ->searchable()
                                                ->columnSpan(3)
                                                ->afterStateUpdated(function ($state, callable $set, callable $get) use ($centerId) {
                                                    if ($state) {
                                                        $price = ServicePrice::where('service_id', $state)
                                                            ->where('center_id', $centerId)
                                                            ->where('is_active', 1)
                                                            ->value('price') ?? 0;
                                                        $set('price', $price);
                                                        $set('amount', 1);
                                                    } else {
                                                        $set('price', 0);
                                                        $set('amount', 1);
                                                    }
                                                }),

                                            TextInput::make('amount')
                                                ->label(__('general.labels.quantity'))
                                                ->numeric()
                                                ->default(1)
                                                ->minValue(1)
                                                ->required()
                                                ->live()
                                                ->columnSpan(1),

                                            Hidden::make('price')
                                                ->default(0),
                                        ])->columns(6),
                                ])
                                ->addActionLabel('+ إضافة خدمة جديدة')
                                ->defaultItems(0)
                                ->minItems(0)
                                ->columnSpanFull(),

                            Placeholder::make('grand_total_placeholder')
                                ->label('')
                                ->content(function (callable $get) use ($centerId) {
                                    $services = $get('services') ?? [];
                                    $dayServices = $get('day_services') ?? [];
                                    $total = 0;
                                    $itemsCount = 0;

                                    foreach ($services as $service) {
                                        if (! empty($service['service_id'])) {
                                            $price = (float) ($service['price'] ?? 0);
                                            $amount = (int) ($service['amount'] ?? 1);
                                            $total += ($price * $amount);
                                            $itemsCount++;
                                        }
                                    }

                                    if (! empty($dayServices)) {
                                        $dayPrices = ServicePrice::where('center_id', $centerId)
                                            ->where('is_active', 1)
                                            ->whereIn('service_id', $dayServices)
                                            ->pluck('price', 'service_id');

                                        foreach ($dayServices as $serviceId) {
                                            $total += (float) ($dayPrices[$serviceId] ?? 0);
                                            $itemsCount++;
                                        }
                                    }

                                    if ($itemsCount === 0) {
                                        return null;
                                    }

                                    return view('filament.forms.components.grand-total', [
                                        'total' => $total,
                                        'count' => $itemsCount,
                                    ]);
                                })
                                ->visible(fn (callable $get) => ! empty($get('services') ?? []) || ! empty($get('day_services') ?? []))
                                ->live(),
                        ];
                    })
                    ->action(function (array $data, Action $action) {
                        $record = $this->getOwnerRecord();
                        $centerId = (int) $record->center_id;

                        $services = array_values(array_filter($data['services'] ?? [], fn ($s) => ! empty($s['service_id'])));
                        $dayServices = $data['day_services'] ?? [];

                        // Resolve prices from the DB, never from (hidden, client-editable) form state
                        $selectedServiceIds = array_unique(array_merge(array_column($services, 'service_id'), $dayServices));
                        $prices = ServicePrice::activePricesFor($centerId, $selectedServiceIds);

                        if (! ServiceHelper::ensureServicesPriced($selectedServiceIds, $prices)) {
                            $action->halt();
                        }

                        DB::transaction(function () use ($record, $services, $dayServices, $prices) {
                            // 1. Add daily services
                            foreach ($dayServices as $serviceId) {
                                EntryService::create([
                                    'entry_id' => $record->id,
                                    'service_id' => $serviceId,
                                    'amount' => 1,
                                    'price_at_time' => $prices[$serviceId],
                                    'total' => $prices[$serviceId],
                                ]);
                            }

                            // 2. Add other services
                            foreach ($services as $serviceData) {
                                $price = $prices[$serviceData['service_id']];
                                $amount = max(1, (int) ($serviceData['amount'] ?? 1));

                                EntryService::create([
                                    'entry_id' => $record->id,
                                    'service_id' => $serviceData['service_id'],
                                    'amount' => $amount,
                                    'price_at_time' => $price,
                                    'total' => $price * $amount,
                                ]);
                            }
                        });

                        // ─── NEW: Sync reference columns after adding ───
                        $this->syncEntryReferenceColumns();

                        // 3. Dispatch event to refresh main form's Grand Total
                        $this->dispatch('entry-services-updated');

                        Notification::make()
                            ->title(__('general.labels.added_successfully'))
                            ->body('تم إضافة الخدمات الجديدة وتحديث الإجمالي.')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->after(function () {
                        // ─── NEW: Sync reference columns after deletion ───
                        $this->syncEntryReferenceColumns();

                        $this->dispatch('entry-services-updated');
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->after(function () {
                            // ─── NEW: Sync reference columns after bulk deletion ───
                            $this->syncEntryReferenceColumns();

                            $this->dispatch('entry-services-updated');
                        }),
                ]),
            ]);
    }
}
