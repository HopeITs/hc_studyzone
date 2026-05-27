<?php

namespace App\Filament\Resources\EntryResource\RelationManagers;

use App\Models\EntryService;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'services';

    protected static ?string $recordTitleAttribute = 'service.name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Keep minimal — the real form is in the custom header action
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('service.name')
            ->columns([
                Tables\Columns\TextColumn::make('service.name')
                    ->label('الخدمة')
                    ->searchable(),
                Tables\Columns\TextColumn::make('service.category.name')
                    ->label('نوع الخدمة')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->formatStateUsing(fn (Model $record) => $record->amount.' '.__('general.services_module.units.'.$record->service->category->unit))
                    ->label('الكمية')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_at_time')
                    ->label('سعر القطعة')
                    ->money('SYP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('السعر الاجمالي')
                    ->money('SYP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->formatStateUsing(fn (Model $record) => $record->total * 100)
                    ->label('السعر الاجمالي')
                    ->money('SYP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإضافة')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([
                Action::make('addServices')
                    ->label('إضافة خدمات')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('شراء خدمات إضافية')
                    ->modalSubmitActionLabel('إضافة وتحديث الإجمالي')
                    ->form(function () {
                        $record = $this->getOwnerRecord();
                        $centerId = $record->center_id;

                        // Existing daily service IDs already linked to this entry
                        $existingDailyServiceIds = $record->services()
                            ->whereHas('service.category', fn ($q) => $q->where('unit', 'day'))
                            ->pluck('service_id')
                            ->toArray();

                        return [
                            CheckboxList::make('day_services')
                                ->label('خدمات يومية (تُطلب لمرة واحدة)')
                                ->options(function () use ($centerId, $existingDailyServiceIds) {
                                    if (! $centerId) {
                                        return [];
                                    }

                                    return Service::where('is_active', 1)
                                        ->whereHas('category', fn ($q) => $q->where('unit', 'day'))
                                        ->whereHas('centerPrices', fn ($q) => $q->where('center_id', $centerId)->where('is_active', 1))
                                        ->whereNotIn('id', $existingDailyServiceIds)
                                        ->with(['category', 'centerPrices' => fn ($q) => $q->where('center_id', $centerId)->where('is_active', 1)])
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
                                ->label('خدمات أخرى')
                                ->schema([
                                    Grid::make()
                                        ->schema([
                                            Select::make('category_id')
                                                ->label('التصنيف')
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
                                                ->label('الخدمة')
                                                ->options(function (callable $get) use ($centerId) {
                                                    $categoryId = $get('category_id');
                                                    if (! $categoryId || ! $centerId) {
                                                        return [];
                                                    }

                                                    return Service::where('category_id', $categoryId)
                                                        ->where('is_active', 1)
                                                        ->whereHas('centerPrices', fn ($q) => $q->where('center_id', $centerId)->where('is_active', 1))
                                                        ->with(['centerPrices' => fn ($q) => $q->where('center_id', $centerId)->where('is_active', 1)])
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
                                                ->label('الكمية')
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
                    ->action(function (array $data) {
                        $record = $this->getOwnerRecord();
                        $centerId = $record->center_id;

                        // 1. Add daily services
                        if (! empty($data['day_services'])) {
                            $dayPrices = ServicePrice::where('center_id', $centerId)
                                ->where('is_active', 1)
                                ->whereIn('service_id', $data['day_services'])
                                ->pluck('price', 'service_id');

                            foreach ($data['day_services'] as $serviceId) {
                                $price = (float) ($dayPrices[$serviceId] ?? 0);
                                EntryService::create([
                                    'entry_id' => $record->id,
                                    'service_id' => $serviceId,
                                    'amount' => 1,
                                    'price_at_time' => $price,
                                    'total' => $price,
                                ]);
                            }
                        }

                        // 2. Add other services
                        if (! empty($data['services'])) {
                            foreach ($data['services'] as $serviceData) {
                                if (! empty($serviceData['service_id'])) {
                                    $price = (float) ($serviceData['price'] ?? 0);
                                    $amount = (int) ($serviceData['amount'] ?? 1);

                                    EntryService::create([
                                        'entry_id' => $record->id,
                                        'service_id' => $serviceData['service_id'],
                                        'amount' => $amount,
                                        'price_at_time' => $price,
                                        'total' => $price * $amount,
                                    ]);
                                }
                            }
                        }

                        // 3. Dispatch event to refresh main form's Grand Total
                        $this->dispatch('entry-services-updated');

                        Notification::make()
                            ->title('تمت الإضافة بنجاح')
                            ->body('تم إضافة الخدمات الجديدة وتحديث الإجمالي.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->after(fn () => $this->dispatch('entry-services-updated')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(fn () => $this->dispatch('entry-services-updated')),
                ]),
            ]);
    }
}
