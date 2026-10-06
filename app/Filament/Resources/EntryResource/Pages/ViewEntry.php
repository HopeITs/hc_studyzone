<?php

namespace App\Filament\Resources\EntryResource\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Fieldset;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use App\Enums\Roles;
use App\Filament\Resources\EntryResource;
use App\Models\Center;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;

class ViewEntry extends ViewRecord
{
    protected static string $resource = EntryResource::class;

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
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(3),

                // ═══════════════════════════════════════════════════════
                // Services Fieldset
                // ═══════════════════════════════════════════════════════
                Fieldset::make('الخدمات المطلوبة')
                    ->schema([


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
            ]);
    }

    // تجاوز دالة التعبئة لعرض البيانات المتعلقة بالعلاقات المتقدمة (Repeater & Checkboxes) بشكل صحيح
    protected function fillForm(): void
    {
        $entry = $this->getRecord();

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
            'entry_time' => $entry->entry_time,
            'services' => $regularServices,
            'day_services' => $dayServiceIds,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
