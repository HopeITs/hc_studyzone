<?php

namespace App\Filament\Resources\EntryResource\Pages;

use App\Filament\Resources\EntryResource;
use App\Models\EntryService;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\StudentEntry;
use Filament\Actions\Action;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AddEntryServices extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = EntryResource::class;
    protected static string $view = 'filament.resources.entry-resource.pages.add-entry-services';

    public ?StudentEntry $record = null;
    public array $formData = [];

    public function mount(StudentEntry $record): void
    {
        $this->record = $record;

        $this->form->fill([
            'day_services' => [],
            'services'     => [],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema($this->getFormSchema())
            ->statePath('formData');
    }

    protected function getFormSchema(): array
    {
        $centerId = (int) $this->record->center_id;
        $entryId  = (int) $this->record->id;

        return [
            // ── Daily Services ──────────────────────────────────────────
            CheckboxList::make('day_services')
                ->label(__('general.labels.daily_services'))
                ->options(function () use ($centerId, $entryId) {
                    // Exclude already-added daily services for this entry
                    $existingIds = EntryService::where('entry_id', $entryId)
                        ->whereHas('service.category', fn($q) => $q->where('unit', 'day'))
                        ->pluck('service_id')
                        ->toArray();

                    return Service::where('is_active', 1)
                        ->whereNotIn('id', $existingIds)
                        ->whereHas('category', fn($q) => $q->where('unit', 'day'))
                        ->whereHas('centerPrices', fn($q) => $q
                            ->where('center_id', $centerId)
                            ->where('is_active', 1)
                        )
                        ->with([
                            'category',
                            'centerPrices' => fn($q) => $q
                                ->where('center_id', $centerId)
                                ->where('is_active', 1),
                        ])
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(function ($service) {
                            $price        = $service->centerPrices->first()?->price ?? 0;
                            $categoryName = $service->category?->name ?? '';

                            return [
                                $service->id => "{$categoryName}: {$service->name} — " . number_format($price) . ' ل.س/يوم',
                            ];
                        });
                })
                ->columns(2)
                ->searchable()
                ->bulkToggleable()
                ->live()
                ->columnSpanFull(),

            // ── Additional Services (Repeater) ───────────────────────────
            Repeater::make('services')
                ->label(__('general.labels.additional_services'))
                ->defaultItems(0)
                ->minItems(0)
                ->addActionLabel('+ '.__('general.labels.add_new_service'))
                ->live()
                ->deleteAction(
                    fn(FormAction $action) => $action
                        ->requiresConfirmation()
                        ->modalHeading(__('general.labels.confirm_delete'))
                        ->modalDescription(__('general.labels.delete_service_question'))
                        ->modalCancelActionLabel(__('general.labels.cancel'))
                        ->modalSubmitActionLabel(__('general.labels.delete'))
                )
                ->itemLabel(function (array $state) {
                    if (empty($state['service_id'])) return '⚠️ '.__('general.labels.unspecified_service');

                    $serviceName = Service::find($state['service_id'])?->name ?? 'خدمة';
                    $amount      = (int) ($state['amount'] ?? 1);
                    $subtotal    = (float) ($state['subtotal'] ?? 0);

                    return "{$serviceName} × {$amount} = " . number_format($subtotal) . ' ل.س';
                })
                ->schema([
                    Grid::make(7)->schema([

                        Select::make('category_id')
                            ->label(__('general.labels.category'))
                            ->placeholder(__('general.labels.select_category_first'))
                            ->options(function () use ($centerId) {
                                return ServiceCategory::where(fn($q) => $q
                                    ->whereNull('unit')
                                    ->orWhere('unit', '!=', 'day')
                                )
                                ->whereHas('services', fn($q) => $q
                                    ->where('is_active', 1)
                                    ->whereHas('centerPrices', fn($pq) => $pq
                                        ->where('center_id', $centerId)
                                        ->where('is_active', 1)
                                    )
                                )
                                ->orderBy('name')
                                ->pluck('name', 'id');
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
                            ->options(function (callable $get) use ($centerId) {
                                $categoryId = $get('category_id');
                                if (!$categoryId) return [];

                                return Service::where('category_id', $categoryId)
                                    ->where('is_active', 1)
                                    ->whereHas('centerPrices', fn($q) => $q
                                        ->where('center_id', $centerId)
                                        ->where('is_active', 1)
                                    )
                                    ->with(['centerPrices' => fn($q) => $q
                                        ->where('center_id', $centerId)
                                        ->where('is_active', 1)
                                    ])
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(function ($service) {
                                        $price = $service->centerPrices->first()?->price ?? 0;

                                        return [
                                            $service->id => "{$service->name} — " . number_format($price) . ' ل.س',
                                        ];
                                    });
                            })
                            ->required()
                            ->live()
                            ->searchable()
                            ->columnSpan(4)
                            ->afterStateUpdated(function ($state, callable $set) use ($centerId) {
                                if ($state) {
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

                        Hidden::make('price'),

                        TextInput::make('amount')
                            ->label(__('general.labels.quantity'))
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(999)
                            ->required()
                            ->live(debounce: 500)
                            ->columnSpan(1)
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $price  = (float) ($get('price') ?? 0);
                                $amount = max(1, (int) ($get('amount') ?? 1));
                                $set('subtotal', $price * $amount);
                            }),

                        Hidden::make('subtotal')->default(0),
                    ]),
                ]),

            // ── Grand Total ──────────────────────────────────────────────
            Placeholder::make('grand_total_placeholder')
                ->label('')
                ->live()
                ->visible(fn(callable $get) =>
                    !empty($get('services') ?? []) || !empty($get('day_services') ?? [])
                )
                ->content(function (callable $get) use ($centerId) {
                    $services    = $get('services') ?? [];
                    $dayServices = $get('day_services') ?? [];
                    $total       = 0;
                    $itemsCount  = 0;

                    // Repeater services
                    foreach ($services as $service) {
                        if (!empty($service['service_id'])) {
                            $total += (float) ($service['price'] ?? 0) * (int) ($service['amount'] ?? 1);
                            $itemsCount++;
                        }
                    }

                    // Daily services — fetch prices fresh from DB (same pattern as working class)
                    if (!empty($dayServices)) {
                        $dayPrices = ServicePrice::where('center_id', $centerId)
                            ->where('is_active', 1)
                            ->whereIn('service_id', $dayServices)
                            ->pluck('price', 'service_id');

                        foreach ($dayServices as $serviceId) {
                            $total += (float) ($dayPrices[$serviceId] ?? 0);
                            $itemsCount++;
                        }
                    }

                    if ($itemsCount === 0) return null;

                    return view('filament.forms.components.grand-total', [
                        'total' => $total,
                        'count' => $itemsCount,
                    ]);
                }),
        ];
    }



  public function saveServices(): void
{
    $data        = $this->form->getState();
    $record      = $this->record;
    $centerId    = (int) $record->center_id;
    $now         = now();
    $rows        = [];

    $services    = $data['services'] ?? [];
    $dayServices = $data['day_services'] ?? [];

    // Fetch day prices fresh from DB
    $dayPrices = [];
    if (!empty($dayServices)) {
        $dayPrices = ServicePrice::where('center_id', $centerId)
            ->where('is_active', 1)
            ->whereIn('service_id', $dayServices)
            ->pluck('price', 'service_id')
            ->toArray();
    }

    foreach ($dayServices as $serviceId) {
        $price  = (float) ($dayPrices[$serviceId] ?? 0);
        $rows[] = [
            'entry_id'      => $record->id,
            'service_id'    => $serviceId,
            'amount'        => 1,
            'price_at_time' => $price,
            'total'         => $price,
            'created_at'    => $now,
            'updated_at'    => $now,
        ];
    }

    foreach ($services as $s) {
        if (!empty($s['service_id'])) {
            $price  = (float) ($s['price'] ?? 0);
            $amount = (int) ($s['amount'] ?? 1);
            $rows[] = [
                'entry_id'      => $record->id,
                'service_id'    => $s['service_id'],
                'amount'        => $amount,
                'price_at_time' => $price,
                'total'         => $price * $amount,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }
    }

    if (!empty($rows)) {
        DB::table('entry_services')->insert($rows);
    }

    // ── NEW: Update reference columns in student_entries ──────────────────

    // 1. Collect all service IDs that were just saved
    $allSavedServiceIds = array_unique(array_merge(
        $dayServices,
        array_map(fn($s) => $s['service_id'], array_filter($services, fn($s) => !empty($s['service_id'])))
    ));

    // 2. Get the unique reference_column_name(s) for these services
    $referenceColumns = Service::whereIn('id', $allSavedServiceIds)
        ->whereNotNull('reference_column_name')
        ->pluck('reference_column_name')
        ->unique()
        ->filter() // Removes null/empty values
        ->toArray();

    // 3. Dynamically prepare the update array for student_entries
    $entryUpdates = [];
    foreach ($referenceColumns as $column) {
        $entryUpdates[$column] = true;
    }

    // 4. Apply the updates to the StudentEntry record
    if (!empty($entryUpdates)) {
        $record->update($entryUpdates);
    }

    // ── END NEW ──────────────────────────────────────────────────────────

    Notification::make()->title(__('general.labels.saved_successfully'))->success()->send();
    $this->redirect(EntryResource::getUrl('index'));
}
}
