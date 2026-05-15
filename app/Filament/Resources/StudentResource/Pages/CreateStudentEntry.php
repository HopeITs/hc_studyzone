<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Enums\Roles;
use App\Filament\Resources\StudentResource;
use App\Models\EntryService;
use App\Models\ServicePrice;
use App\Models\StudentEntry;
use Filament\Actions;
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
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Illuminate\Support\Facades\Auth;

class CreateStudentEntry extends Page
{
    use InteractsWithRecord;

    protected static string $resource = StudentResource::class;

    protected static string $view = 'filament.resources.student-resource.pages.create-student-entry';

    public ?array $data = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_if(! $this->record->completed_data, 403, 'بيانات الطالب غير مكتملة');

        $this->form->fill([
            'center_id'   => auth()->user()->center_id,
            'entry_type'  => 1,
            'entry_time'  => now(),
            'services'    => [],
            'day_services'=> [],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([

                // ═══════════════════════════════════════════════════════
                // Entry Details Fieldset
                // ═══════════════════════════════════════════════════════
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

                // ═══════════════════════════════════════════════════════
                // Services Fieldset
                // ═══════════════════════════════════════════════════════
                Fieldset::make('الخدمات المطلوبة')
                    ->schema([

                        // ─── Day Services (CheckboxList) ───
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

                        // ─── Info Text ───
                        Placeholder::make('services_help')
                            ->label('')
                            ->content('اختر التصنيف ثم الخدمة التابعة لهذا المركز')
                            ->visible(fn (callable $get) => empty($get('services') ?? []) && empty($get('day_services') ?? []))
                            ->columnSpanFull(),

                        // ─── Regular Services (Repeater) ───
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

                        // ─── Grand Total ───
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

    public function create(): void
    {
        $data = $this->form->getState();

        $centerId = $data['center_id'] ?? auth()->user()->center_id;

        // Calculate total
        $totalAmount = 0;

        // Regular services
        $services = $data['services'] ?? [];
        foreach ($services as $serviceData) {
            $price = (float) ($serviceData['price'] ?? 0);
            $quantity = (int) ($serviceData['quantity'] ?? 1);
            $totalAmount += ($price * $quantity);
        }

        // Day services
        $dayServices = $data['day_services'] ?? [];
        $dayPrices = [];
        if (! empty($dayServices)) {
            $dayPrices = ServicePrice::where('center_id', $centerId)
                ->where('is_active', 1)
                ->whereIn('service_id', $dayServices)
                ->pluck('price', 'service_id')
                ->toArray();

            foreach ($dayServices as $serviceId) {
                $totalAmount += (float) ($dayPrices[$serviceId] ?? 0);
            }
        }

        // Create entry
        $entry = StudentEntry::create([
            'student_id'   => $this->record->id,
            'user_id'      => Auth::id(),
            'center_id'    => $centerId,
            'entry_type'   => $data['entry_type'],
            'entry_time'   => $data['entry_time'],

        ]);

        // Attach regular services
        foreach ($services as $serviceData) {
            if (! empty($serviceData['service_id'])) {
                EntryService::create([
                    'entry_id'   => $entry->id,
                    'service_id' => $serviceData['service_id'],
                    'amount'     => (int) ($serviceData['quantity'] ?? 1),
                    'price_at_time' => (float) ($serviceData['price'] ?? 0),
                    'total'      => (float) ($serviceData['price'] ?? 0) * (int) ($serviceData['quantity'] ?? 1),
                ]);
            }
        }

        // Attach day services (quantity = 1)
        foreach ($dayServices as $serviceId) {
            $price = (float) ($dayPrices[$serviceId] ?? 0);
            EntryService::create([
                'entry_id'      => $entry->id,
                'service_id'    => $serviceId,
                'amount'        => 1,
                'price_at_time' => $price,
                'total'         => $price,
            ]);
        }

        Notification::make()
            ->title(__('general.labels.entry_recorded_successfully'))
            ->success()
            ->send();

        $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
    }

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         Actions\CancelAction::make()
    //             ->url(static::getResource()::getUrl('index')),
    //     ];
    // }
}
