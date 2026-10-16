<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Enums\EntryPurpose;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Fieldset;
use Carbon\Carbon;
use Filament\Schemas\Components\Grid;
use Filament\Actions\Action;
use App\Enums\Roles;
use App\Filament\Resources\StudentResource;
use App\Helpers\ServiceHelper;
use App\Models\Center;
use App\Models\EntryService;
use App\Models\LockedMonth;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\StudentEntry;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CreateStudentEntry extends Page
{
    use InteractsWithRecord;

    protected static string $resource = StudentResource::class;

    protected string $view = 'filament.resources.student-resource.pages.create-student-entry';

    public ?array $data = [];

    // This will hold the entry instance if we are editing
    public ?StudentEntry $entryInstance = null;

    // Guards against a second click firing after the button is re-enabled
    // by the Livewire re-render that follows a successful save, but before
    // the browser has actually navigated away.
    public bool $saved = false;

    public function getHeading(): string
    {
        return $this->entryInstance ? 'تعديل الدخولية' : 'إضافة دخولية جديدة';
    }

    public function mount(...$args): void
    {
        $record = $args['record'] ?? $args[0] ?? null;
        $entry = $args['entry'] ?? $args[1] ?? null;

        $this->record = $this->resolveRecord($record);

        abort_if(! $this->record->completed_data, 403, 'بيانات الطالب غير مكتملة');

        if ($entry) {
            $this->entryInstance = StudentEntry::where('student_id', $this->record->id)
                ->findOrFail($entry);
            $this->fillFormForEdit();
        } else {
            $this->form->fill([
                'center_id' => auth()->user()->center_id,
                'entry_type' => 1,
                'purpose' => EntryPurpose::Studying(),
                'entry_time' => now(),
                'services' => [],
                'day_services' => [],
            ]);
        }
    }

    protected function fillFormForEdit(): void
    {
        $entry = $this->entryInstance;

        // Separate day services from regular services
        $dayServiceIds = $entry->services
            ->filter(fn ($es) => $es->service && $es->service->category && $es->service->category->unit === 'day')
            ->pluck('service_id')
            ->toArray();

        $regularServices = $entry->services
            ->filter(fn ($es) => ! $es->service || ! $es->service->category || $es->service->category->unit !== 'day')
            ->map(fn ($es) => [
                'category_id' => $es->service->category_id ?? null,
                'service_id' => $es->service_id,
                'amount' => $es->amount,
                'price' => $es->price_at_time,
                'subtotal' => $es->total,
            ])
            ->values()
            ->toArray();

        $this->form->fill([
            'center_id' => $entry->center_id,
            'entry_type' => $entry->entry_type,
            'purpose' => $entry->purpose,
            'entry_time' => $entry->entry_time,
            'services' => $regularServices,
            'day_services' => $dayServiceIds,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ═══════════════════════════════════════════════════════
                // Entry Details Fieldset
                // ═══════════════════════════════════════════════════════
                Fieldset::make('تفاصيل الدخول')
                    ->schema([
                        Select::make('center_id')
                            ->label(__('general.labels.center'))
                            ->options(function () {
                                return Center::where('is_active', 1)
                                    ->when(
                                        auth()->user()->city_id,
                                        fn ($query, $cityId) => $query->where('city_id', $cityId)
                                    )
                                    ->pluck('name', 'id');
                            })
                            ->default(fn () => auth()->user()->center_id)
                            ->visible(fn () => auth()->user()->role !== Roles::CenterAdmin())
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (callable $set) {
                                $set('services', []);
                                $set('day_services', []);
                            })
                            ->columnSpan(1),

                        Hidden::make('_center_id_for_calc')
                            ->default(fn () => auth()->user()->center_id)
                            ->dehydrateStateUsing(fn ($state, callable $get) => $get('center_id') ?? auth()->user()->center_id),

                        Select::make('entry_type')
                            ->label(__('general.labels.entry_type'))
                            ->default(1)
                            ->options(__('general.entry-types'))
                            ->required()
                            ->columnSpan(1),

                        DateTimePicker::make('entry_time')
                            ->label(__('general.labels.entry_time'))
                            ->default(now())
                            ->maxDate(now()->endOfDay())
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                if (! $state || Carbon::parse($state)->isToday()) {
                                    return;
                                }

                                Notification::make()
                                    ->title(__('general.messages.non_today_entry_date_title'))
                                    ->body(__('general.messages.non_today_entry_date_body'))
                                    ->warning()
                                    ->send();
                            })
                            ->required()
                            ->columnSpan(1),

                        Radio::make('purpose')
                            ->label(__('general.labels.purpose'))
                            ->options([
                                EntryPurpose::Studying() => __('general.purposes.'.EntryPurpose::Studying()),
                                EntryPurpose::Working() => __('general.purposes.'.EntryPurpose::Working()),
                                EntryPurpose::Unknown() => __('general.purposes.'.EntryPurpose::Unknown()),
                            ])
                            ->default(EntryPurpose::Studying())
                            ->inline()
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(4),

                // ═══════════════════════════════════════════════════════
                // Services Fieldset
                // ═══════════════════════════════════════════════════════
                Fieldset::make('الخدمات المطلوبة')
                    ->schema([

                            CheckboxList::make('day_services')
                            ->label(__('general.labels.one_time_daily_services'))
                            ->options(function (callable $get) {
                                $centerId = $get('center_id')
                                    ?? $get('_center_id_for_calc')
                                    ?? auth()->user()->center_id;

                                if (! $centerId) {
                                    return [];
                                }

                                return Service::where('is_active', 1)
                                    ->whereHas('category', fn ($q) => $q->where('unit', 'day'))
                                    ->whereHas('centerPrices', fn ($q) => $q->where('center_id', $centerId)->where('is_active', 1))
                                    ->with([
                                        'category',
                                        'centerPrices' => fn ($q) => $q->where('center_id', $centerId)->where('is_active', 1),
                                    ])
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(function ($service) {
                                        $price = $service->centerPrices->first()?->price ?? 0;
                                        $categoryName = $service->category?->name ?? '';

                                        return [
                                            $service->id => "{$categoryName}: {$service->name} — ".number_format($price).' ل.س/يوم',
                                        ];
                                    });
                            })
                            ->visible(function (callable $get) {
                                $centerId = $get('center_id')
                                    ?? $get('_center_id_for_calc')
                                    ?? auth()->user()->center_id;

                                if (! $centerId) {
                                    return false;
                                }

                                return ServiceCategory::where('unit', 'day')
                                    ->whereHas('services', function ($q) use ($centerId) {
                                        $q->where('is_active', 1)
                                            ->whereHas('centerPrices', fn ($pq) => $pq->where('center_id', $centerId)->where('is_active', 1));
                                    })
                                    ->exists();
                            })
                            ->live()
                            ->searchable()
                            ->columns(2)
                            ->bulkToggleable()
                            ->columnSpanFull(),

                        Placeholder::make('services_help')
                            ->label('')
                            ->content('اختر التصنيف ثم الخدمة التابعة لهذا المركز')
                            ->visible(fn (callable $get) => empty($get('services') ?? []) && empty($get('day_services') ?? []))
                            ->columnSpanFull(),

                        Repeater::make('services')
                            ->label('')
                            ->schema([
                                Grid::make()
                                    ->schema([
                                            Select::make('category_id')
                                            ->label(__('general.labels.category'))
                                            ->placeholder(__('general.labels.select_category_first'))
                                            ->options(function (callable $get) {
                                                $centerId = $get('../../center_id')
                                                    ?? $get('../../_center_id_for_calc')
                                                    ?? auth()->user()->center_id;

                                                if (! $centerId) {
                                                    return [];
                                                }

                                                return ServiceCategory::where(function ($q) {
                                                    $q->whereNull('unit')
                                                        ->orWhere('unit', '!=', 'day');
                                                })
                                                    ->whereHas('services', function ($serviceQuery) use ($centerId) {
                                                        $serviceQuery->where('is_active', 1)
                                                            ->whereHas('centerPrices', function ($priceQuery) use ($centerId) {
                                                                $priceQuery->where('center_id', $centerId)
                                                                    ->where('is_active', 1);
                                                            });
                                                    })
                                                    ->withCount([
                                                        'services as available_services_count' => function ($query) use ($centerId) {
                                                            $query->whereHas('centerPrices', function ($q) use ($centerId) {
                                                                $q->where('center_id', $centerId)
                                                                    ->where('is_active', 1);
                                                            });
                                                        },
                                                    ])
                                                    ->having('available_services_count', '>', 0)
                                                    ->orderBy('name')
                                                    ->get()
                                                    ->mapWithKeys(function ($category) {
                                                        return [
                                                            $category->id => "{$category->name} ({$category->available_services_count} خدمة)",
                                                        ];
                                                    });
                                            })
                                            ->required()
                                            ->live()
                                            ->searchable()
                                            ->columnSpan(2)
                                            ->afterStateUpdated(function (callable $set) {
                                                $set('service_id', null);
                                                $set('price', null);
                                                $set('amount', 1);
                                                $set('subtotal', 0);
                                            }),

                                            Select::make('service_id')
                                            ->label(__('general.labels.service'))
                                            ->placeholder(__('general.labels.select_category_first'))
                                            ->options(function (callable $get) {
                                                $centerId = $get('../../center_id')
                                                    ?? $get('../../_center_id_for_calc')
                                                    ?? auth()->user()->center_id;
                                                $categoryId = $get('category_id');

                                                if (! $categoryId || ! $centerId) {
                                                    return [];
                                                }

                                                return Service::where('category_id', $categoryId)
                                                    ->where('is_active', 1)
                                                    ->whereHas('centerPrices', function ($query) use ($centerId) {
                                                        $query->where('center_id', $centerId)
                                                            ->where('is_active', 1);
                                                    })
                                                    ->with(['centerPrices' => function ($query) use ($centerId) {
                                                        $query->where('center_id', $centerId)
                                                            ->where('is_active', 1);
                                                    }])
                                                    ->orderBy('name')
                                                    ->get()
                                                    ->mapWithKeys(function ($service) {
                                                        $price = $service->centerPrices->first()?->price ?? 0;
                                                        $formattedPrice = number_format($price);

                                                        return [
                                                            $service->id => "{$service->name} — {$formattedPrice} ل.س",
                                                        ];
                                                    });
                                            })
                                            ->required()
                                            ->live()
                                            ->searchable()
                                            ->columnSpan(4)
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                if ($state) {
                                                    $centerId = $get('../../center_id')
                                                        ?? $get('../../_center_id_for_calc')
                                                        ?? auth()->user()->center_id;

                                                    $price = ServicePrice::where('service_id', $state)
                                                        ->where('center_id', $centerId)
                                                        ->where('is_active', 1)
                                                        ->value('price') ?? 0;

                                                    $set('price', $price);
                                                    $set('amount', 1);
                                                    $set('subtotal', $price);
                                                } else {
                                                    $set('price', null);
                                                    $set('amount', 1);
                                                    $set('subtotal', 0);
                                                }
                                            }),

                                            TextInput::make('amount')
                                            ->label(__('general.labels.quantity'))
                                            ->placeholder('1')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(1)
                                            ->maxValue(999)
                                            ->required()
                                            ->live()
                                            ->columnSpan(1)
                                            ->afterStateUpdated(function (callable $get, callable $set) {
                                                $price = (float) ($get('price') ?? 0);
                                                $amount = max(1, (int) ($get('amount') ?? 1));
                                                $set('subtotal', $price * $amount);
                                            }),
                                    ])
                                    ->columns(7),
                            ])
                            ->columns(1)
                            ->addActionLabel('+ إضافة خدمة جديدة')
                            ->itemLabel(function (array $state) {
                                if (empty($state['service_id'])) {
                                    return '⚠️ خدمة غير محددة';
                                }

                                $serviceName = Service::find($state['service_id'])?->name ?? 'خدمة';
                                $amount = (int) ($state['amount'] ?? 1);
                                $subtotal = (float) ($state['subtotal'] ?? 0);

                                return "{$serviceName} × {$amount} = ".number_format($subtotal).' ل.س';
                            })
                            ->live()
                            ->defaultItems(0)
                            ->minItems(0)
                            ->deleteAction(
                                fn (Action $action) => $action
                                    ->requiresConfirmation()
                                    ->modalHeading(__('general.labels.confirm_delete'))
                                    ->modalDescription(__('general.labels.delete_service_question'))
                                    ->modalCancelActionLabel('إلغاء')
                                    ->modalSubmitActionLabel('حذف')
                            ),

                        Placeholder::make('grand_total_placeholder')
                            ->label('')
                            ->content(function (callable $get) {
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
                                    $centerId = $get('center_id')
                                        ?? $get('_center_id_for_calc')
                                        ?? auth()->user()->center_id;

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
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        if ($this->saved) {
            return;
        }

        $lockKey = 'student-entry-save-'.$this->record->id.($this->entryInstance?->id ? '-'.$this->entryInstance->id : '');
        $lock = Cache::lock($lockKey, 10);

        if (! $lock->get()) {
            return;
        }

        try {
            $this->persist();
        } finally {
            $lock->release();
        }
    }

    protected function persist(): void
    {
        $data = $this->form->getState();

        $lockedDate = collect([
            $data['entry_time'] ?? null,
            $this->entryInstance?->entry_time,
        ])->filter(fn ($date) => $date && LockedMonth::isLocked($date))->first();

        if ($lockedDate) {
            Notification::make()
                ->title(__('general.messages.month_locked_title'))
                ->body(__('general.messages.month_locked_body'))
                ->danger()
                ->send();

            return;
        }

        $centerId = (int) ($data['center_id'] ?? auth()->user()->center_id);

        $services = array_values(array_filter($data['services'] ?? [], fn ($s) => ! empty($s['service_id'])));
        $dayServices = $data['day_services'] ?? [];
        $selectedServiceIds = array_unique(array_merge(array_column($services, 'service_id'), $dayServices));

        // Prices are always resolved here: the repeater's `price` key is not a form
        // field, so it never survives getState(), and client state must not set prices.
        $prices = ServicePrice::activePricesFor($centerId, $selectedServiceIds);

        // When editing within the same center, keep what each existing service was charged.
        if ($this->entryInstance && (int) $this->entryInstance->center_id === $centerId) {
            $prices = $this->entryInstance->services()
                ->where('price_at_time', '>', 0)
                ->pluck('price_at_time', 'service_id')
                ->all() + $prices;
        }

        if (! ServiceHelper::ensureServicesPriced($selectedServiceIds, $prices)) {
            return;
        }

        DB::transaction(fn () => $this->saveEntryWithServices($data, $centerId, $services, $dayServices, $prices));

        $this->saved = true;

        Notification::make()
            ->title($this->entryInstance ? 'تم تحديث الدخولية بنجاح' : __('general.labels.entry_recorded_successfully'))
            ->success()
            ->send();

        $this->redirect(static::getResource()::getUrl('index'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $services
     * @param  array<int|string>  $dayServices
     * @param  array<int, int>  $prices  service_id => unit price
     */
    protected function saveEntryWithServices(array $data, int $centerId, array $services, array $dayServices, array $prices): void
    {
        // ─── Update or Create Entry ───
        if ($this->entryInstance) {
            $this->entryInstance->update([
                'center_id' => $centerId,
                'entry_type' => $data['entry_type'],
                'purpose' => $data['purpose'],
                'entry_time' => $data['entry_time'],
            ]);

            $entry = $this->entryInstance;

            // Delete old services to re-sync them cleanly
            $entry->services()->delete();
        } else {
            $entry = StudentEntry::create([
                'student_id' => $this->record->id,
                'user_id' => Auth::id(),
                'center_id' => $centerId,
                'entry_type' => $data['entry_type'],
                'purpose' => $data['purpose'],
                'entry_time' => $data['entry_time'],
            ]);
        }

        // ─── Re-attach regular services ───
        foreach ($services as $serviceData) {
            $price = $prices[$serviceData['service_id']];
            $amount = max(1, (int) ($serviceData['amount'] ?? 1));

            EntryService::create([
                'entry_id' => $entry->id,
                'service_id' => $serviceData['service_id'],
                'amount' => $amount,
                'price_at_time' => $price,
                'total' => $price * $amount,
            ]);
        }

        // ─── Re-attach day services ───
        foreach ($dayServices as $serviceId) {
            $price = $prices[$serviceId];
            EntryService::create([
                'entry_id' => $entry->id,
                'service_id' => $serviceId,
                'amount' => 1,
                'price_at_time' => $price,
                'total' => $price,
            ]);
        }

        // ─── NEW: Sync reference_column_name to student_entries ───

        // 1. Collect all service IDs that were just saved
        $allSavedServiceIds = array_unique(array_merge(array_column($services, 'service_id'), $dayServices));

        // 2. Fetch all possible reference columns to reset removed ones to false (handles edit scenario)
        $allPossibleColumns = Service::whereNotNull('reference_column_name')
            ->pluck('reference_column_name')
            ->unique()
            ->filter()
            ->toArray();

        // Initialize all possible columns to false
        $entryUpdates = array_fill_keys($allPossibleColumns, false);

        // 3. Fetch the reference_column_name(s) for the currently selected services
        $activeReferenceColumns = Service::whereIn('id', $allSavedServiceIds)
            ->whereNotNull('reference_column_name')
            ->pluck('reference_column_name')
            ->unique()
            ->filter()
            ->toArray();

        // Override the active columns to true
        foreach ($activeReferenceColumns as $column) {
            $entryUpdates[$column] = true;
        }

        // 4. Update the StudentEntry record
        if (!empty($entryUpdates)) {
            $entry->update($entryUpdates);
        }

        // ─── END NEW ───
    }
}
