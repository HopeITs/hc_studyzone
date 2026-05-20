<?php

namespace App\Filament\Resources;

use App\Enums\Gender;
use App\Enums\Religion;
use App\Enums\Roles;
use App\Filament\Exports\EntryExporter;
use App\Filament\Resources\EntryResource\Pages;
use App\Filament\Resources\EntryResource\RelationManagers\ServicesRelationManager;
use App\Filament\Resources\EntryResource\Widgets\EntryStats;
use App\Models\Education;
use App\Models\Service;
use App\Models\EntryService;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\StudentEducation;
use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EntryResource extends Resource
{
    protected static ?string $model = StudentEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'الدخوليات';

    protected static ?string $label = 'دخولية';

    protected static ?string $pluralModelLabel = 'الدخوليات';

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        if ($user->role === Roles::GeneralAdmin() || $user->role === Roles::Observer()) {
            return parent::getEloquentQuery();
        }

        if ($user->role === Roles::CityAdmin() || $user->role === Roles::MealAdmin()) {
            return parent::getEloquentQuery()->whereHas('center', function ($query) use ($user) {
                $query->where('city_id', $user->city_id);
            });
        }

        if ($user->role === Roles::CenterAdmin()) {
            return parent::getEloquentQuery()->where('center_id', $user->center_id);
        }

        return parent::getEloquentQuery()->whereRaw('1 = 0');
    }

    public static function table(Table $table): Table
    {
        $isObserver = auth()->user()->role === Roles::Observer();
        $isMealAdmin = auth()->user()->role === Roles::MealAdmin();

        return $table
            ->columns([
                TextColumn::make('student.firstname')->label('اسم الطالب')->formatStateUsing(fn (Model $record) => $record->student->firstname.' '.$record->student->father_name.' '.$record->student->lastname)->sortable()->searchable(
                    query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('student', function (Builder $studentQuery) use ($search) {
                            $studentQuery
                                ->where('firstname', 'like', "%{$search}%")
                                ->orWhere('lastname', 'like', "%{$search}%")
                                ->orWhere('father_name', 'like', "%{$search}%");
                        });
                    },
                ),
                IconColumn::make('student.completed_data')->label('حالة البيانات')->toggleable(isToggledHiddenByDefault: true)->boolean()->trueIcon('heroicon-o-check-circle')->falseIcon('heroicon-o-x-circle')->trueColor('success')->falseColor('danger')->sortable()->tooltip(fn ($record) => $record->student->completed_data ? 'البيانات مكتملة' : 'البيانات غير مكتملة'),
                TextColumn::make('student.national_number')->label('الرقم الوطني / الإقامة')->sortable()->toggleable(),
                TextColumn::make('student.phone')->label('الموبايل')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('student.gender')
                    ->label('الجنس')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("general.genders.{$state}"))
                    ->color(
                        fn (int $state): string => match ($state) {
                            Gender::Unknown() => 'warning',
                            Gender::Male() => 'info',
                            Gender::Female() => 'danger',
                        },
                    )
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('student.religion')
                    ->label('الديانة')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("general.religions.{$state}"))
                    ->color(
                        fn (int $state): string => match ($state) {
                            Religion::Unknown() => 'warning',
                            Religion::Christian() => 'success',
                            Religion::Muslim() => 'secondary',
                        },
                    )
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('student.whatsapp')->label('واتساب')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('entry_type')->label('نوع الدخول')->sortable()->formatStateUsing(fn ($state) => __('general.entry-types.'.$state)),
                TextColumn::make('student.studentEducations.level')
                    ->label('مستوى التعليم')
                    ->formatStateUsing(function ($record) {
                        $lastEducation = $record->student->studentEducations()
                            ->orderBy('start_date', 'desc')
                            ->first();

                        if (! $lastEducation) {
                            return '-';
                        }

                        $levelValue = $lastEducation->level;

                        return __('general.edu-levels.'.(string) $levelValue);
                    })
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(
                        query: function (Builder $query, string $direction): Builder {
                            return $query->orderBy(
                                StudentEducation::select('level')
                                    ->whereColumn('student_id', 'students.id')
                                    ->orderBy('start_date', 'desc')
                                    ->limit(1),
                                $direction
                            );
                        },
                    )
                    ->searchable(
                        query: function (Builder $query, string $search): Builder {
                            return $query->whereHas('student.studentEducations', function (Builder $studentQuery) use ($search) {
                                $studentQuery->where('level', 'like', "%{$search}%");
                            });
                        },
                    ),

                TextColumn::make('student.education')
                    ->label('المؤهل العلمي')
                    ->sortable(
                        query: function (Builder $query, string $direction): Builder {
                            return $query->orderBy(
                                StudentEducation::select('education_id')
                                    ->whereColumn('student_id', 'students.id')
                                    ->orderBy('start_date', 'desc')
                                    ->limit(1),
                                $direction
                            );
                        },
                    )
                    ->toggleable(),
                TextColumn::make('services_list')
                    ->label('الخدمات المقدمة')
                    ->formatStateUsing(fn ($record) => $record->services_list ?: '-')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('total_amount')
                    ->label('الإجمالي')
                    ->formatStateUsing(fn ($record) => $record->total_amount.' SYP')
                    ->sortable()
                    ->color('success')
                    ->toggleable(),
                TextColumn::make('entry_time')->label('تاريخ الدخول')->sortable()->dateTime('Y-m-d H:i'),
                TextColumn::make('center.city.name')
                    ->label('المدينة')
                    ->sortable()
                    ->visible(auth()->user()->role === Roles::GeneralAdmin())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('center.name')
                    ->label('المركز')
                    ->sortable()
                    ->hidden(auth()->user()->role === Roles::CenterAdmin())
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersFormColumns(4)
            ->filters([
                        Filter::make('entry_time')
                                ->label('التاريخ')
                                ->form([
                                    DateTimePicker::make('from')
                                        ->label('من')
                                        ->default(today()->startOfDay())
                                        ->columnSpan(1),
                                    DateTimePicker::make('to')
                                        ->label('إلى')
                                        ->default(today()->endOfDay())
                                        ->columnSpan(1),
                                ])
                                ->columnSpan(2)
                                ->columns(2)
                                ->query(function (Builder $query, array $data): Builder {

                                    return $query->when($data['from'], fn ($q, $date) => $q->where('entry_time', '>=', $date))->when($data['to'], fn ($q, $date) => $q->where('entry_time', '<=', $date));
                                }),
                        SelectFilter::make('entry_type')->label('نوع الدخولية')->options(__('general.entry-types'))->searchable()->multiple()->preload()->columnSpan(1),
                        TernaryFilter::make('student.completed_data')
                                ->label('حالة بيانات الطالب')
                                ->placeholder('الكل')
                                ->trueLabel('نعم - البيانات مكتملة')
                                ->falseLabel('لا - البيانات غير مكتملة')
                                ->queries(
                                    true: fn (Builder $query) => $query->whereHas('student', function ($query) {
                                        $query->where('completed_data', true);
                                    }),
                                    false: fn (Builder $query) => $query->whereHas('student', function ($query) {
                                        $query->where('completed_data', false);
                                    }),
                                    blank: fn (Builder $query) => $query,
                                )
                                ->columnSpan(1),

                        SelectFilter::make('center.city.name')
                                ->label('المدينة')
                                ->relationship('center.city', 'name')
                                ->searchable()
                                ->columnSpan(1)
                                ->visible(auth()->user()->role === Roles::GeneralAdmin())
                                ->multiple()
                                ->preload(),
                        SelectFilter::make('center_id')
                                ->label('المركز')
                                ->relationship('center', 'name')
                                ->searchable()
                                ->columnSpan(auth()->user()->role === Roles::GeneralAdmin() ? 1 : 4)
                                ->hidden(auth()->user()->role === Roles::CenterAdmin())
                                ->multiple()
                                ->preload(),

                        SelectFilter::make('student.education')
                                ->label('المؤهل العلمي')
                                ->options(Education::pluck('name', 'id'))
                                ->searchable()
                                ->multiple()
                                ->preload()
                                ->columnSpan(2)
                                ->query(function (Builder $query, array $data, $livewire): Builder {
                                    if (empty($data['values'])) {
                                        return $query;
                                    }

                                    $entryTimeData = $livewire->tableFilters['entry_time'] ?? null;

                                    $from = $entryTimeData['from'] ?? null;
                                    $to = $entryTimeData['to'] ?? null;

                                    return $query->whereHas('student.studentEducations', function (Builder $studentQuery) use ($data, $from, $to) {

                                        $studentQuery->whereIn('education_id', (array) $data['values']);

                                        if ($from && $to) {
                                            $studentQuery->where(function ($q) use ($from, $to) {
                                                $q->whereColumn('start_date', '<=', 'entry_time')
                                                    ->where(function ($subQ) {
                                                        $subQ->whereColumn('end_date', '>=', 'entry_time')
                                                            ->orWhereNull('end_date');
                                                    });
                                                $q->whereBetween('entry_time', [Carbon::parse($from), Carbon::parse($to)]);
                                            });
                                        }
                                    });
                                }),

                        SelectFilter::make('student.studentEducations.level')
                                ->label(__('general.labels.education_level'))
                                ->options(__('general.edu-levels'))
                                ->searchable()
                                ->columnSpan(1)
                                ->query(function (Builder $query, array $data): Builder {
                                    if (array_key_exists('value', $data) && $data['value'] !== null && $data['value'] !== '') {
                                        return $query->whereHas('student.studentEducations', function (Builder $studentQuery) use ($data) {
                                            $studentQuery->where('level', (int) $data['value']);
                                        });
                                    }

                                    return $query;
                                })
                                ->preload(),

                        TernaryFilter::make('student.national_number')->label('الرقم الوطني / الإقامة')->placeholder('الكل')->trueLabel('يوجد')->falseLabel('لا يوجد')->queries(
                true: fn (Builder $query) => $query->whereHas('student', function ($query) {
                    $query->whereNotNull('national_number');
                }),
                false: fn (Builder $query) => $query->whereHas('student', function ($query) {
                    $query->whereNull('national_number');
                }),
                blank: fn (Builder $query) => $query,
                    )->columnSpan(1),
                    SelectFilter::make('student.gender')
                            ->label('الجنس')
                            ->options(__('general.genders'))
                            ->preload()
                            ->query(function (Builder $query, array $data): Builder {
                                if (array_key_exists('value', $data) && $data['value'] !== null && $data['value'] !== '') {
                                    return $query->whereHas('student', function (Builder $studentQuery) use ($data) {
                                        $studentQuery->where('gender', (int) $data['value']);
                                    });
                                }

                                return $query;
                            }),

                    SelectFilter::make('student.religion')
                            ->label('الديانة')
                            ->options(__('general.religions'))
                            ->preload()
                            ->query(function (Builder $query, array $data): Builder {
                                if (array_key_exists('value', $data) && $data['value'] !== null && $data['value'] !== '') {
                                    return $query->whereHas('student', function (Builder $studentQuery) use ($data) {
                                        $studentQuery->where('religion', (int) $data['value']);
                                    });
                                }

                                return $query;
                            }),
                ])
            ->filtersTriggerAction(fn (Action $action) => $action->button()->label(''))
            ->actions([
            ViewAction::make('view_student')->label('')->color('info')->icon('heroicon-o-user')->url(fn (Model $record) => StudentResource::getUrl('view', ['record' => $record->student_id])),

            // ✅ زر إضافة خدمات إضافية في سطر الجدول
            Action::make('addServices')
                    ->label('')
                    ->icon('heroicon-o-shopping-cart')

                    ->tooltip('إضافة خدمات جديدة')
                    ->modalHeading('شراء خدمات إضافية')
                    ->modalSubmitActionLabel('إضافة وتحديث الإجمالي')
                    ->visible(fn () => auth()->user()->role !== Roles::Observer())
                    ->form(function (Model $record) {
                        $centerId = $record->center_id;

                        // 1. جلب معرفات الخدمات اليومية التي تم طلبها مسبقاً في هذه الدخولية
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
                                        ->whereNotIn('id', $existingDailyServiceIds) // <--- استبعاد الخدمات المطلوبة مسبقاً
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
                                                        $price = ServicePrice::where('service_id', $state)->where('center_id', $centerId)->where('is_active', 1)->value('price') ?? 0;
                                                        $set('price', $price);
                                                        $set('amount', 1);
                                                    } else {
                                                        $set('price', null);
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
                                        ])->columns(6),
                                ])
                                ->addActionLabel('+ إضافة خدمة جديدة')
                                ->defaultItems(0)
                                ->minItems(0)
                                ->columnSpanFull(),
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
                        ];
                    })
                    ->action(function (array $data, Model $record) {
                        $centerId = $record->center_id;

                        // 1. إضافة الخدمات اليومية
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

                        // 2. إضافة الخدمات العادية
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

                        // 3. إعادة حساب الإجمالي الكلي وتحديث الدخولية
                       // $totalAmount = $record->services()->sum('total');
                       // $record->update(['total' => $totalAmount]);

                        Notification::make()
                            ->title('تمت الإضافة بنجاح')
                            ->body('تم إضافة الخدمات الجديدة وتحديث الإجمالي.')
                            ->success()
                            ->send();
                    }),

                    Tables\Actions\EditAction::make()->label('')
                    ->hidden(fn () => auth()->user()->role === Roles::Observer())
                    ->disabled(function ($record) {
                        return ! $record->student->completed_data;
                    })
                    ->tooltip(function ($record) {
                        if (! $record->student->completed_data) {
                            return '❌ لا يمكن التعديل: بيانات الطالب غير مكتملة';
                        }

                        return null;
                    }),
            Tables\Actions\DeleteAction::make()->label('')->hidden(fn () => auth()->user()->role === Roles::Observer()),
        ])
            ->bulkActions([
            ExportBulkAction::make()
                    ->exporter(EntryExporter::class)
                    ->formats([ExportFormat::Xlsx]),
            Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()->hidden(fn () => auth()->user()->role === Roles::Observer())])->hidden(fn () => auth()->user()->role === Roles::Observer()),
        ])
            ->paginated([20, 50, 100, 200]);
    }

    public static function getRelations(): array
    {
        return [
            ServicesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEntries::route('/'),
            'edit' => Pages\EditEntry::route('/{record}/edit'),
            'view' => Pages\ViewEntry::route('/{record}'),
        ];
    }

    public static function getWidgets(): array
    {
        return [EntryStats::class];
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->role !== Roles::MealAdmin() && auth()->user()->role !== Roles::Observer();
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->role !== Roles::MealAdmin() && auth()->user()->role !== Roles::Observer();
    }
}
