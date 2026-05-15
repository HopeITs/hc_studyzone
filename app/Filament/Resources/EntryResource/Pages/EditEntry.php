<?php

namespace App\Filament\Resources\EntryResource\Pages;

use App\Filament\Resources\EntryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Enums\Roles;
use App\Models\EntryService;
use App\Models\ServicePrice;
use App\Models\StudentEntry;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditEntry extends EditRecord
{
    protected static string $resource = EntryResource::class;

    protected static string $view = 'filament.resources.student-resource.pages.create-student-entry';

    public ?array $data = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_if(! $this->record->student->completed_data, 403, 'بيانات الطالب غير مكتملة');

        // ── Load existing regular services (non-day) ──
        $existingServices = $this->record->services()
            ->whereHas('service.category', fn ($q) =>
                $q->where('unit', '!=', 'day')->orWhereNull('unit')
            )
            ->with('service')
            ->get();

        // ── Load existing day services ──
        $existingDayServices = $this->record->services()
            ->whereHas('service.category', fn ($q) => $q->where('unit', 'day'))
            ->pluck('service_id')
            ->toArray();

        $servicesData = $existingServices->map(function ($es) {
            return [
                'category_id' => $es->service->category_id ?? null,
                'service_id'  => $es->service_id,
                'price'       => $es->price_at_time,
                'quantity'    => $es->amount,
                'subtotal'    => $es->total,
            ];
        })->values()->toArray();

        // ── Fill form with EXISTING record data ──
        $this->form->fill([
            'center_id'    => $this->record->center_id,
            'entry_type'   => $this->record->entry_type,
            'entry_time'   => $this->record->entry_time,
            'services'     => $servicesData,
            'day_services' => $existingDayServices,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([

                Fieldset::make('تفاصيل الدخول')
                    ->schema([
                        Select::make('center_id')
                            ->label(__('general.labels.center'))
                            ->options(function () {
                                return \App\Models\Center::where('is_active', 1)
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
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(3),

                Fieldset::make('الخدمات المطلوبة')
                    ->schema([

                        CheckboxList::make('day_services')
                            ->label('خدمات يومية (تُطلب لمرة واحدة)')
                            ->options(function (callable $get) {
                                $centerId = $get('center_id')
                                    ?? $get('_center_id_for_calc')
                                    ?? auth()->user()->center_id;

                                if (! $centerId) {
                                    return [];
                                }

                                return \App\Models\Service::where('is_active', 1)
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
                                            $service->id => "{$categoryName}: {$service->name} — " . number_format($price) . ' ل.س/يوم',
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

                                return \App\Models\ServiceCategory::where('unit', 'day')
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
                                            ->label('التصنيف')
                                            ->placeholder('اختر التصنيف أولاً')
                                            ->options(function (callable $get) {
                                                $centerId = $get('../../center_id')
                                                    ?? $get('../../_center_id_for_calc')
                                                    ?? auth()->user()->center_id;

                                                if (! $centerId) {
                                                    return [];
                                                }

                                                return \App\Models\ServiceCategory::
                                                    where(function ($q) {
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
                                                $set('quantity', 1);
                                                $set('subtotal', 0);
                                            }),

                                        Select::make('service_id')
                                            ->label('الخدمة')
                                            ->placeholder('اختر التصنيف أولاً')
                                            ->options(function (callable $get) {
                                                $centerId = $get('../../center_id')
                                                    ?? $get('../../_center_id_for_calc')
                                                    ?? auth()->user()->center_id;
                                                $categoryId = $get('category_id');

                                                if (! $categoryId || ! $centerId) {
                                                    return [];
                                                }

                                                return \App\Models\Service::where('category_id', $categoryId)
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
                                                    $set('quantity', 1);
                                                    $set('subtotal', $price);
                                                } else {
                                                    $set('price', null);
                                                    $set('quantity', 1);
                                                    $set('subtotal', 0);
                                                }
                                            }),

                                        TextInput::make('quantity')
                                            ->label('الكمية')
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
                                                $quantity = max(1, (int) ($get('quantity') ?? 1));
                                                $set('subtotal', $price * $quantity);
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

                                $serviceName = \App\Models\Service::find($state['service_id'])?->name ?? 'خدمة';
                                $quantity = (int) ($state['quantity'] ?? 1);
                                $subtotal = (float) ($state['subtotal'] ?? 0);

                                return "{$serviceName} × {$quantity} = " . number_format($subtotal) . ' ل.س';
                            })
                            ->live()
                            ->defaultItems(0)
                            ->minItems(0)
                            ->deleteAction(
                                fn (\Filament\Forms\Components\Actions\Action $action) => $action
                                    ->requiresConfirmation()
                                    ->modalHeading('تأكيد الحذف')
                                    ->modalDescription('هل تريد حذف هذه الخدمة؟')
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
                                        $quantity = (int) ($service['quantity'] ?? 1);
                                        $total += ($price * $quantity);
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

    /**
     * Replace the old create() method — this is called by EditRecord's save flow.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $centerId = $data['center_id'] ?? auth()->user()->center_id;

        // ── Update entry basic info ──
        $record->update([
            'center_id'  => $centerId,
            'entry_type' => $data['entry_type'],
            'entry_time' => $data['entry_time'],
        ]);

        // ── Delete old services ──
        $record->services()->delete();

        // ── Re-attach regular services ──
        $services = $data['services'] ?? [];
        foreach ($services as $serviceData) {
            if (! empty($serviceData['service_id'])) {
                EntryService::create([
                    'entry_id'      => $record->id,
                    'service_id'    => $serviceData['service_id'],
                    'amount'        => (int) ($serviceData['quantity'] ?? 1),
                    'price_at_time' => (float) ($serviceData['price'] ?? 0),
                    'total'         => (float) ($serviceData['price'] ?? 0) * (int) ($serviceData['quantity'] ?? 1),
                ]);
            }
        }

        // ── Re-attach day services ──
        $dayServices = $data['day_services'] ?? [];
        $dayPrices = [];
        if (! empty($dayServices)) {
            $dayPrices = ServicePrice::where('center_id', $centerId)
                ->where('is_active', 1)
                ->whereIn('service_id', $dayServices)
                ->pluck('price', 'service_id')
                ->toArray();
        }

        foreach ($dayServices as $serviceId) {
            $price = (float) ($dayPrices[$serviceId] ?? 0);
            EntryService::create([
                'entry_id'      => $record->id,
                'service_id'    => $serviceId,
                'amount'        => 1,
                'price_at_time' => $price,
                'total'         => $price,
            ]);
        }

        return $record;
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title(__('general.labels.entry_recorded_successfully'))
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
