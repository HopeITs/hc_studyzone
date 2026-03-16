<?php

namespace App\Filament\Resources;

use App\Enums\Roles;
use App\Filament\Exports\StudentExporter;
use App\Filament\Resources\StudentResource\Pages;
use App\Filament\Resources\StudentResource\RelationManagers;
use App\Models\Center;
use App\Models\City;
use App\Models\Street;
use App\Models\Student;
use App\Models\StudentEntry;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use UnitEnum; // Import this for type checking

class StudentResource extends Resource
{
    use InteractsWithPageFilters;

    protected static ?string $model = Student::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 6;

    protected static ?string $label = 'طالب';

    protected static ?string $modelLabel = 'طالب';

    protected static ?string $navigationLabel = 'الطلاب';

    protected static ?string $pluralModelLabel = 'الطلاب';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('educations');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([Wizard::make([self::personalAndContactInformationStep(), self::educationStep()])->columnSpanFull()]);
    }

    protected static function personalAndContactInformationStep(): Wizard\Step
    {
        return Wizard\Step::make(__('general.labels.personal_information').' & '.__('general.labels.contact_information'))
            ->schema(
                fn (?Student $record) => [
                    \Filament\Forms\Components\Placeholder::make('data_status_notice')
                        ->label('ملاحظة')
                        ->content(function (?Student $record) {
                            if (! $record) {
                                return '';
                            }

                            if (! $record->completed_data) {
                                $errors = $record->getEntryValidationErrors();
                                $message = "⚠️ **استكمل البيانات الخاصة للطالب قبل تسجيل الدخولية.**\n\n**الحقول الناقصة:**";

                                if (! empty($errors)) {
                                    $message .= "\n";
                                    foreach ($errors as $error) {
                                        $message .= "• {$error}\n";
                                    }
                                }

                                return $message;
                            }

                            return '✅ **جميع البيانات مكتملة - يمكن تسجيل الدخوليات**';
                        })
                        ->hidden(fn (?Student $record): bool => $record === null)
                        ->columnSpanFull()
                        ->extraAttributes(['class' => 'bg-gray-50 dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 whitespace-pre-line']),

                    TextInput::make('firstname')->label(__('general.labels.firstname'))->required()->maxLength(255)->columnSpan(1),
                    TextInput::make('lastname')->label(__('general.labels.lastname'))->required()->maxLength(255)->columnSpan(1),
                    TextInput::make('father_name')->label(__('general.labels.father_name'))->required()->maxLength(255)->columnSpan(1),

                    \Filament\Forms\Components\Grid::make(3)->schema([
                        Select::make('nationality')
                            ->label(__('general.labels.nationality'))
                            ->options(__('general.nationalities'))
                            ->default('syrian')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('national_number', null);
                                $set('skip_national_number', false);
                                $set('skip_national_number_reason', null);
                            }),

                        \Filament\Forms\Components\Grid::make(2)
                            ->columnSpan(2)
                            ->schema([
                                Checkbox::make('is_child')
                                    ->label('قاصر')
                                    ->default(false)
                                    ->live()
                                    ->afterStateHydrated(function ($set, $get, $state, $livewire) {
                                        $record = $livewire->record ?? null;
                                        if ($record && is_null($record->national_number) && ! $record->skip_national_number) {
                                            $set('is_child', true);
                                        }
                                    }),

                                Checkbox::make('skip_national_number')
                                    ->label('تخطي الرقم الوطني')
                                    ->helperText('الموافقة على إضافة طالب بدون رقم وطني')
                                    ->visible(fn () => in_array(auth()->user()->role, [Roles::GeneralAdmin(), Roles::CityAdmin()]))
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        if ($state) {
                                            $set('national_number', null);
                                        } else {
                                            $set('skip_national_number_reason', null);
                                        }
                                    }),
                            ]),
                    ]),

                    Textarea::make('skip_national_number_reason')->label('سبب تخطي الرقم الوطني')->placeholder('يرجى كتابة سبب عدم إدخال الرقم الوطني...')->required(fn (Get $get) => $get('skip_national_number') === true)->visible(fn (Get $get) => in_array(auth()->user()->role, [Roles::GeneralAdmin(), Roles::CityAdmin()]) && $get('skip_national_number') === true)->columnSpan(3)->maxLength(500),

                    TextInput::make('national_number')
                        ->label(fn (Get $get) => $get('nationality') === 'syrian' ? __('general.labels.national_number') : __('general.labels.foreign_id_number'))
                        ->required(fn (Get $get) => ! $get('is_child') && ! $get('skip_national_number'))
                        ->disabled(fn (Get $get) => $get('is_child') || $get('skip_national_number'))
                        ->numeric(fn (Get $get) => $get('nationality') === 'syrian')
                        ->live()
                        ->rules([
                            fn (Get $get): string => $get('nationality') === 'syrian' && ! $get('skip_national_number') ? 'digits:11' : '',
                            fn (Get $get) => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                                if (empty($value) || $get('skip_national_number')) {
                                    return;
                                }

                                $student = Student::where('national_number', $value)->first();

                                if ($student && (! $record || $student->id !== $record->id)) {
                                    Notification::make()
                                        ->title("الطالب {$student->firstname} موجود في محافظة {$student->street->city->name}")
                                        ->danger()
                                        ->persistent()
                                        ->actions([NotificationAction::make('view')->button()->label('عرض الطالب')->url(route('filament.admin.resources.students.view', $student), shouldOpenInNewTab: true)])
                                        ->send();

                                    $fail("الطالب {$student->firstname} موجود مسبقاً.");
                                }
                            },
                        ])
                        ->validationMessages([
                            'digits' => 'يجب إدخال 11 خانة بالضبط للرقم الوطني.',
                        ])
                        ->columnSpan(2),

                    Select::make('gender')->label(__('general.labels.gender'))->options(__('general.genders'))->required()->columnSpan(1),
                    Select::make('religion')->label(__('general.labels.religion'))->options(__('general.religions'))->default(0)->columnSpan(1)->required(),
                    DatePicker::make('year_of_birth')->label(__('general.labels.date_of_birth'))->columnSpan(1),
                    Toggle::make('is_student')->label(__('general.labels.is_student'))->columnSpan(1),
                    Toggle::make('is_employed')->label(__('general.labels.is_employed'))->columnSpan(1),
                    Textarea::make('notes')->label(__('general.labels.notes'))->maxLength(500)->columnSpan(3),
                    Select::make('city_id')
                        ->live()
                        ->label(__('general.labels.city'))
                        ->dehydrated(false)
                        ->searchable()
                        ->default(auth()->user()->city_id ?? '')
                        ->preload()
                        ->options(City::pluck('name', 'id'))
                        ->afterStateUpdated(fn ($state, callable $set) => $set('street_id', null)),
                    Select::make('street_id')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->label(__('general.labels.street'))
                        ->placeholder(fn (Get $get): string => empty($get('city_id')) ? 'First select City' : 'Select an option')
                        ->options(function (?Student $record, Get $get, Set $set) {
                            $cityId = $get('city_id');
                            if (! empty($record) && empty($cityId)) {
                                $cityId = $record->street->city_id ?? '';
                                $set('city_id', $cityId);
                                $set('street_id', $record->street_id);
                            }

                            return Street::where('city_id', $cityId)->pluck('name', 'id');
                        }),
                    TextInput::make('phone')
                        ->label(__('general.labels.phone'))
                        ->required()
                        ->rules([
                            fn (Get $get) => function (string $attribute, $value, Closure $fail) use ($get) {
                                if ($get('nationality') === 'syrian' && ! preg_match('/^09\d{8}$/', $value)) {
                                    $fail('رقم الهاتف السوري يجب أن يبدأ بالرقم 09 ويحتوي على 10 أرقام.');
                                }
                            },
                        ])
                        ->validationMessages([
                            'required' => 'رقم الهاتف مطلوب.',
                        ]),
                    TextInput::make('whatsapp')
                        ->label(__('general.labels.whatsapp'))
                        ->rules([
                            fn (Get $get) => function (string $attribute, $value, Closure $fail) use ($get) {
                                if ($value && $get('nationality') === 'syrian' && ! preg_match('/^09\d{8}$/', $value)) {
                                    $fail('رقم الواتساب السوري يجب أن يبدأ بالرقم 09 ويحتوي على 10 أرقام.');
                                }
                            },
                        ]),
                    TextInput::make('email')->label(__('general.labels.email'))->unique(ignoreRecord: true),
                    TextInput::make('address')->label(__('general.labels.address'))->maxLength(255),
                ],
            )
            ->columns(3);
    }

    // protected static function educationStep(): Wizard\Step
    // {
    //     return Wizard\Step::make(__('general.labels.education'))
    //         ->schema([
    //             \Filament\Forms\Components\Repeater::make('educations')
    //                 ->relationship('studentEducations')
    //                 ->label(__('general.labels.educations'))
    //                 ->schema([
    //                     Select::make('degree')
    //                         ->label('الدرجة')
    //                         ->options(__('general.edu-degrees'))
    //                         ->reactive()
    //                         ->afterStateUpdated(fn (callable $set) => $set('education_id', null))
    //                         ->dehydrated(false)
    //                         ->afterStateHydrated(function ($set, $state, $record) {
    //                             if ($record && $record->education) {
    //                                 $set('degree', $record->education->degree);
    //                             }
    //                         }),
    //                     Select::make('education_id')
    //                         ->label(__('general.labels.education'))
    //                         ->options(function (callable $get) {
    //                             $degree = $get('degree');

    //                             return $degree ? \App\Models\Education::where('degree', $degree)->pluck('name', 'id') : \App\Models\Education::pluck('name', 'id');
    //                         })
    //                         ->searchable()
    //                         ->required(),
    //                     Select::make('level')->label(__('general.labels.education_level'))->options(__('general.edu-levels'))->required(),
    //                     Select::make('type')->label(__('general.labels.education_type'))->options(__('general.edu-types'))->required(),

    //                     \Filament\Forms\Components\DatePicker::make('start_date')
    //                         ->label('تاريخ البداية')
    //                         ->default(now())
    //                         ->required()
    //                         ->displayFormat('Y-m-d'),

    //                     \Filament\Forms\Components\DatePicker::make('end_date')
    //                         ->label('تاريخ النهاية')
    //                         ->nullable()
    //                         ->after('start_date')
    //                         ->helperText('اتركه فارغاً إذا كانت الدراسة مستمرة')
    //                         ->maxDate(now())
    //                         ->displayFormat('Y-m-d'),
    //                 ])
    //                 ->columns(2)
    //                 ->minItems(1)
    //                 ->addActionLabel(__('general.labels.add_education'))
    //                 ->columnSpanFull(),
    //         ])
    //         ->columns(1);
    // }

protected static function educationStep(): Wizard\Step
{
    return Wizard\Step::make(__('general.labels.education'))
        ->schema([
            \Filament\Forms\Components\Repeater::make('educations')
                ->relationship('studentEducations')
                ->label(__('general.labels.educations'))
                ->schema([
                    Select::make('degree')
                        ->label('الدرجة')
                        ->options(__('general.edu-degrees'))
                        ->reactive()
                        ->afterStateUpdated(fn(callable $set) => $set('education_id', null))
                        ->dehydrated(false)
                        ->afterStateHydrated(function ($set, $state, $record) {
                            if ($record && $record->education) {
                                $set('degree', $record->education->degree);
                            }
                        }),
                    Select::make('education_id')
                        ->label(__('general.labels.education'))
                        ->options(function (callable $get) {
                            $degree = $get('degree');
                            if ($degree instanceof UnitEnum) {
                                $degree = $degree->value;
                            }
                            return $degree
                                ? \App\Models\Education::where('degree', $degree)->pluck('name', 'id')
                                : \App\Models\Education::pluck('name', 'id');
                        })
                        ->searchable()
                        ->required(),
                    Select::make('level')
                        ->label(__('general.labels.education_level'))
                        ->options(__('general.edu-levels'))
                        ->required(),
                    Select::make('type')
                        ->label(__('general.labels.education_type'))
                        ->options(__('general.edu-types'))
                        ->required(),

                    \Filament\Forms\Components\DatePicker::make('start_date')
                        ->label('تاريخ البداية')
                        ->default(now())
                        ->required()
                        ->displayFormat('Y-m-d'),

                    \Filament\Forms\Components\DatePicker::make('end_date')
                        ->label('تاريخ النهاية')
                        ->nullable()
                        ->after('start_date')
                        ->helperText('اتركه فارغاً إذا كانت الدراسة مستمرة')
                        ->maxDate(now())
                        ->displayFormat('Y-m-d'),
                ])
                ->columns(2)
                ->minItems(1)
                ->addActionLabel(__('general.labels.add_education'))
                ->columnSpanFull()
                ->rules([
                    function () {
                        return function (string $attribute, $value, Closure $fail) {
                            $seenCombinations = [];
                            $groupedByEducation = [];

                            // 1. Prepare data for Timeline and Active checks
                            $timelineItems = [];
                            $activeItems = [];
// 1. Collect all active degrees for the exception check
                            $activeDegrees = [];
                            foreach ($value as $index => $item) {
                                // Basic Data Check
                                if (!isset($item['degree']) || !isset($item['level']) || !isset($item['education_id']) || !isset($item['start_date'])) {
                                    continue;
                                }

                                // A. Date Validation (No Future End Date)
                                if (isset($item['end_date']) && Carbon::parse($item['end_date'])->isFuture()) {
                                    Notification::make()->danger()->title('خطأ')->body('تاريخ النهاية لا يمكن أن يكون في المستقبل.')->send();
                                    $fail('تاريخ النهاية لا يمكن أن يكون في المستقبل.');
                                    return;
                                }

                                $degreeValue = $item['degree'];
                                if ($degreeValue instanceof UnitEnum) {
                                    $degreeValue = $degreeValue->value;
                                }
                                $degreeValue = (int) $degreeValue;

                                // B. Duplicate Degree + Level Check
                                $degreeLevelKey = (string)$degreeValue . '_' . $item['level'] . '_'. $item['education_id'];
                                if (in_array($degreeLevelKey, $seenCombinations)) {
                                    Notification::make()->danger()->title('خطأ')->body('لا يمكن إضافة سجلين بنفس الدرجة والمستوى.')->send();
                                    $fail('لا يمكن إضافة سجلين بنفس الدرجة والمستوى.');
                                    return;
                                }
                                $seenCombinations[] = $degreeLevelKey;

                                // C. Overlap Check (Same Education ID)
                                $eduId = $item['education_id'];
                                if (!isset($groupedByEducation[$eduId])) {
                                    $groupedByEducation[$eduId] = [];
                                }

                                $currentStart = Carbon::parse($item['start_date']);
                                $currentEnd = isset($item['end_date']) ? Carbon::parse($item['end_date']) : null;

                                foreach ($groupedByEducation[$eduId] as $existingItem) {
                                    $existStart = Carbon::parse($existingItem['start_date']);
                                    $existEnd = isset($existingItem['end_date']) ? Carbon::parse($existingItem['end_date']) : now()->addYears(100);

                                    // Use null as "far future" for comparison logic only
                                    $checkEnd = $currentEnd ?? now()->addYears(100);

                                    if ($currentStart->lt($existEnd) && $existStart->lt($checkEnd)) {
                                        Notification::make()->danger()->title('خطأ')->body('يوجد تعارض في التواريخ لنفس التعليم.')->send();
                                        $fail("يوجد تعارض في التواريخ: لا يمكن تسجيل نفس التعليم مرتين في نفس الفترة.");
                                        return;
                                    }
                                }
                                $groupedByEducation[$eduId][] = $item;

                                    // 5. Populate Active Degrees List
                                // An item is active if end_date is null or empty
                                if (!isset($item['end_date']) || empty($item['end_date'])) {
                                    $activeDegrees[] = (int) $degreeValue; // Ensure integer for comparison
                                }

                                // D. Collect for Timeline Check
                                $timelineItems[] = [
                                    'start' => $currentStart,
                                    'end' => $currentEnd, // Can be null
                                    'degree' => $degreeValue,
                                    'name' => __("general.edu-degrees.{$degreeValue}") ?? "Degree {$degreeValue}"
                                ];

                                // E. Collect Active Items
                                if (!$currentEnd) {
                                    $activeItems[] = [
                                        'degree' => $degreeValue,
                                        'start_date' => $currentStart,
                                    ];
                                }
                            }

                            // --- 2. NO GAP VALIDATION ---
                            // Sort items by start date
                            usort($timelineItems, function ($a, $b) {
                                return $a['start']->lt($b['start']) ? -1 : 1;
                            });

                            // Check gaps
                            for ($i = 1; $i < count($timelineItems); $i++) {
                                $prev = $timelineItems[$i - 1];
                                $curr = $timelineItems[$i];

                                // If previous item has an end date, check for gap
                                if ($prev['end']) {
                                    // Condition: Current Start MUST be <= Previous End
                                    // If Current Start > Previous End -> It's a GAP.
                                    if ($curr['start']->gt($prev['end'])) {
                                        Notification::make()
                                            ->danger()
                                            ->title('خطأ: فجوة زمنية')
                                            ->body("لا يمكن وجود فجوة بين الدراسات. دراسة '{$curr['name']}' تبدأ بعد انتهاء دراسة '{$prev['name']}'. يجب أن تكون الدراسة مستمرة.")
                                            ->send();
                                        $fail('لا يمكن وجود فجوة بين الدراسات. يجب أن يبدأ التعليم الجديد قبل أو عند انتهاء التعليم السابق.');
                                        return;
                                    }
                                }
                            }

                        // --- NEW ACTIVE EDUCATION LOGIC ---

                            $activeCount = count($activeDegrees);

                            // Rule A: Must have at least one active education
                            if ($activeCount < 1) {
                                Notification::make()
                                    ->danger()
                                    ->title('خطأ في توحيد البيانات')
                                    ->body('يجب أن تكون هناك على الأقل دراسة واحدة نشطة (بدون تاريخ نهاية).')
                                    ->send();
                                $fail('يجب أن تكون هناك على الأقل دراسة واحدة نشطة (بدون تاريخ نهاية).');
                                return;
                            }

                            // Rule B: Maximum 2 active educations allowed (specifically for the Degree 6 exception)
                            if ($activeCount > 2) {
                                Notification::make()
                                    ->danger()
                                    ->title('خطأ في عدد الدراسات النشطة')
                                    ->body('لا يمكن أن يكون هناك أكثر من دراستين نشطتين في نفس الوقت.')
                                    ->send();
                                $fail('لا يمكن أن يكون هناك أكثر من دراستين نشطتين في نفس الوقت.');
                                return;
                            }

                            // Rule C: If there are 2 active educations, one MUST be Degree 6 (High Secondary)
                            if ($activeCount === 2) {
                                // Check if Degree 6 is present in the active list
                                $hasHighSchoolException = in_array(6, $activeDegrees);

                                if ($hasHighSchoolException) {
                                    Notification::make()
                                        ->danger()
                                        ->title('انتبه في تعدد الدراسات')
                                        ->body(' هناك دراستان نشطتان إحداهما الثانوية العامة.')
                                        ->send();

                                    return;
                                }
                            }
                        };
                    },
                ]),
        ])
        ->columns(1);
}

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => self::filterQueryByRole($query))
            ->columns(self::tableColumns())
            ->filtersFormColumns(2)
            ->filters(self::tableFilters())
            ->recordUrl(function (Student $record): string {
                if (auth()->user()->role === Roles::CenterAdmin()) {
                    return static::getUrl('edit', ['record' => $record]);
                }

                return static::getUrl('view', ['record' => $record]);
            })
            ->toggleColumnsTriggerAction(fn (Action $action) => $action->button())
            ->filtersTriggerAction(fn (Action $action) => $action->button()->label(''))
            ->actions(self::tableActions())
            ->bulkActions([
                ExportBulkAction::make()
                    ->exporter(StudentExporter::class)
                    ->formats([ExportFormat::Xlsx]),
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()->hidden(fn () => auth()->user()->role === Roles::Observer())])->hidden(fn () => auth()->user()->role === Roles::Observer()),
            ])
            ->paginated([20, 50, 100, 200]);
    }

    protected static function tableColumns(): array
    {
        return [
            TextColumn::make('firstname')
                ->label('Full name')
                ->formatStateUsing(fn (Model $record) => $record->firstname.' '.$record->father_name.' '.$record->lastname)
                ->sortable(['firstname', 'lastname'])
                ->searchable(
                    query: function (Builder $query, string $search): Builder {
                        return $query
                            ->where('firstname', 'like', "%{$search}%")
                            ->orWhere('lastname', 'like', "%{$search}%")
                            ->orWhere('father_name', 'like', "%{$search}%");
                    },
                ),
            IconColumn::make('completed_data')->label('حالة البيانات')->boolean()->trueIcon('heroicon-o-check-circle')->falseIcon('heroicon-o-x-circle')->trueColor('success')->falseColor('danger')->sortable()->tooltip(fn ($record) => $record->completed_data ? 'البيانات مكتملة' : 'البيانات غير مكتملة'),
            TextColumn::make('year_of_birth')->label('Date of Birth')->sortable()->toggleable(),
            TextColumn::make('nationality')->label('Nationality')->label('الجنسية')->sortable()->searchable()->toggleable(),
            TextColumn::make('national_number')->label('الرقم الوطني / الإقامة')->sortable()->searchable()->toggleable(),
            TextColumn::make('skip_national_number')->label('تم التخطي')->badge()->formatStateUsing(fn ($state) => $state ? 'نعم' : 'لا')->color(fn ($state) => $state ? 'warning' : 'gray')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('skip_national_number_reason')->label('سبب التخطي')->limit(30)->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('phone')->label('Phone')->sortable()->searchable()->toggleable(),
            TextColumn::make('email')->label('Email')->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('entries_count')->counts('entries')->label('Entries')->sortable()->toggleable(),
            TextColumn::make('whatsapp')->label('Whatsapp')->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('street.name')->label('Street')->toggleable()->sortable()->searchable(),
            TextColumn::make('educations')
                ->label('Education')
                ->formatStateUsing(function (Model $record) {
                    $lastEducation = $record->studentEducations()
                        ->with('education')
                        ->orderBy('start_date', 'desc')
                        ->first();

                    if (! $lastEducation || ! $lastEducation->education) {
                        return '-';
                    }

                    return $lastEducation->education->getTranslation('name', 'ar');
                })
                ->searchable(
                    query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('studentEducations.education', function (Builder $studentQuery) use ($search) {
                            $studentQuery->where('name', 'like', "%{$search}%");
                        });
                    },
                )
                ->sortable(
                    query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            \App\Models\StudentEducation::select('education_id')
                                ->whereColumn('student_id', 'students.id')
                                ->orderBy('start_date', 'desc')
                                ->limit(1),
                            $direction
                        );
                    },
                )
                ->toggleable(),
            TextColumn::make('street.city.name')->label('City')->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('gender')
                ->label('Gender')
                ->badge()
                ->sortable()
                ->formatStateUsing(function ($state) {
                    \Illuminate\Support\Facades\Log::info('Gender value: '.gettype($state).' - '.json_encode($state));

                    return __('general.genders.'.(is_object($state) && method_exists($state, 'value') ? $state->value : $state));
                })
                ->color(
                    function ($state): string {
                        $numericValue = 0;

                        if (is_numeric($state)) {
                            $numericValue = (int) $state;
                        } elseif (is_object($state) && method_exists($state, 'value')) {
                            $numericValue = (int) $state->value;
                        } elseif (is_string($state) && is_numeric($state)) {
                            $numericValue = (int) $state;
                        }

                        return match ($numericValue) {
                            0 => 'warning',
                            1 => 'info',
                            2 => 'danger',
                            default => 'gray',
                        };
                    }
                )
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('religion')
                ->label('Religion')
                ->badge()
                ->sortable()
                ->formatStateUsing(function ($state) {
                    \Illuminate\Support\Facades\Log::info('Religion value: '.gettype($state).' - '.json_encode($state));

                    return __('general.religions.'.(is_object($state) && method_exists($state, 'value') ? $state->value : $state));
                })
                ->color(
                    function ($state): string {
                        $numericValue = 0;

                        if (is_numeric($state)) {
                            $numericValue = (int) $state;
                        } elseif (is_object($state) && method_exists($state, 'value')) {
                            $numericValue = (int) $state->value;
                        } elseif (is_string($state) && is_numeric($state)) {
                            $numericValue = (int) $state;
                        }

                        return match ($numericValue) {
                            0 => 'warning',
                            1 => 'success',
                            2 => 'secondary',
                            default => 'gray',
                        };
                    }
                )
                ->toggleable(isToggledHiddenByDefault: true),
            CheckboxColumn::make('is_student')->toggleable(isToggledHiddenByDefault: true),
            CheckboxColumn::make('is_employed')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('created_at')->label('Created at')->formatStateUsing(fn (Model $record) => $record->created_at->format('Y-m-d'))->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    protected static function qrCodeModalContent(Student $record)
    {
        $url = url("student/{$record->id}/entry");
        $qrCode = QrCode::size(200)->generate($url);

        return view('filament.qr-code-modal', [
            'uuid' => $record->id,
            'url' => $url,
            'qrCode' => $qrCode,
        ]);
    }

    protected static function filterQueryByRole(Builder $query): Builder
    {
        $user = Auth::user();
        if ($user->role === Roles::GeneralAdmin() || $user->role === Roles::Observer()) {
            return $query;
        } else {
            $cityId = $user->city_id;
            $streetsArr = \App\Models\Street::where('city_id', $cityId)->pluck('id')->toArray();
            if (empty($streetsArr)) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where(function ($q) use ($streetsArr, $cityId) {
                $q->whereIn('street_id', $streetsArr)->orWhereHas('entries.center', function ($q) use ($cityId) {
                    $q->where('city_id', $cityId);
                });
            });
        }
    }

    protected static function tableFilters(): array
    {
        if (isset(auth()->user()->city_id)) {
            $streets = Street::where('city_id', auth()->user()->city_id)
                ->get()
                ->pluck('name', 'id')
                ->toArray();
        } else {
            if (isset(request()->query()['tableFilters']['city']['value'])) {
                $streets = Street::where('city_id', request()->query()['tableFilters']['city']['value'])
                    ->get()
                    ->pluck('name', 'id')
                    ->toArray();
            } else {
                $streets = Street::all()->pluck('name', 'id')->toArray();
            }
        }

        return [
            TernaryFilter::make('completed_data')->label('حالة البيانات')->placeholder('الكل')->trueLabel('نعم - البيانات مكتملة')->falseLabel('لا - البيانات غير مكتملة')->queries(true: fn (Builder $query) => $query->where('completed_data', true), false: fn (Builder $query) => $query->where('completed_data', false), blank: fn (Builder $query) => $query),
            SelectFilter::make('city')
                ->relationship('street.city', 'name')
                ->label('المدينة')
                ->preload()
                ->multiple()
                ->options(City::all()->pluck('name', 'id'))
                ->searchable(),
            SelectFilter::make('street_id')->label('الشارع')->preload()->options($streets)->searchable(),
            Filter::make('created_at')
                ->columns(2)
                ->form([DatePicker::make('created_from')->label('تاريخ الإضافة من'), DatePicker::make('created_until')->label('تاريخ الإضافة حتى')])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when($data['created_from'], fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))->when($data['created_until'], fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date));
                }),
            SelectFilter::make('educations')->label('المؤهلات العلمية')->relationship('educations', 'name')->searchable()->multiple()->preload(),
            SelectFilter::make('gender')->label('الجنس')->options(__('general.genders')),
            SelectFilter::make('religion')->label('الديانة')->options(__('general.religions')),
            TernaryFilter::make('national_number')->label('الرقم الوطني / الإقامة')->placeholder('الكل')->trueLabel('يوجد')->falseLabel('لا يوجد')->queries(true: fn (Builder $query) => $query->whereNotNull('national_number'), false: fn (Builder $query) => $query->whereNull('national_number'), blank: fn (Builder $query) => $query),
            TernaryFilter::make('skip_national_number')->label('تم تخطي الرقم الوطني')->placeholder('الكل')->trueLabel('نعم')->falseLabel('لا'),
        ];
    }

    protected static function tableActions(): array
    {
        $isObserver = auth()->user()->role === Roles::Observer();

        return [
            Tables\Actions\Action::make('view_qr_code')->label(__('general.labels.view_qr_code'))->icon('heroicon-o-qr-code')->modalHeading(__('general.labels.qr_code_modal_heading'))->modalContent(fn (Student $record) => self::qrCodeModalContent($record))->modalCancelAction(false)->modalSubmitAction(false)->hidden($isObserver),
            Tables\Actions\Action::make('entry')
                ->label(__('general.labels.entry'))
                ->icon('heroicon-o-document-text')
                ->form([
                    Fieldset::make('تفاصيل')
                        ->schema([
                            Select::make('center_id')
                                ->label(__('general.labels.center'))
                                ->options(function () {
                                    return Center::where('is_active', 1)
                                        ->when(auth()->user()->city_id, fn ($query, $cityId) => $query->where('city_id', $cityId))
                                        ->get()
                                        ->pluck('name', 'id');
                                })
                                ->default(fn () => auth()->user()->center_id)
                                ->visible(auth()->user()->role !== Roles::CenterAdmin())
                                ->required()
                                ->searchable(),
                            Select::make('entry_type')->label(__('general.labels.entry_type'))->default(1)->options(__('general.entry-types'))->columnSpan(1)->required(),
                            DateTimePicker::make('entry_time')->label(__('general.labels.entry_time'))->default(now())->columnSpan(1)->required(),
                        ])
                        ->columns(3),
                    Fieldset::make('Services')
                        ->label('خدمات')
                        ->schema([Checkbox::make('use_printer')->label('خدمة طباعة')->default(false), Checkbox::make('use_drawer')->label('خدمة مرسم')->default(false), Checkbox::make('use_mecrator')->label('خدمة ميكراتور')->default(false), Checkbox::make('use_plotter')->label('خدمة بلوتر')->default(false)])
                        ->columns(4),
                ])
                ->modalHeading(__('general.labels.record_student_entry'))
                ->modalButton(__('general.labels.save_entry'))
                ->action(function (array $data, Student $record) {
                    if (! $record->completed_data) {
                        Notification::make()->title('❌ لا يمكن إدخال بيانات الطالب')->danger()->body('بيانات الطالب غير مكتملة. الرجاء مراجعة البيانات المطلوبة أولاً.')->send();

                        return;
                    }

                    StudentEntry::create([
                        'student_id' => $record->id,
                        'user_id' => Auth::id(),
                        'center_id' => $data['center_id'] ?? auth()->user()->center_id,
                        'entry_type' => $data['entry_type'],
                        'entry_time' => $data['entry_time'],
                        'use_drawer' => $data['use_drawer'],
                        'use_printer' => $data['use_printer'],
                        'use_mecrator' => $data['use_mecrator'],
                        'use_plotter' => $data['use_plotter'],
                    ]);
                })
                ->successNotificationTitle(__('general.labels.entry_recorded_successfully'))
                ->disabled(function (Student $record) {
                    return ! $record->completed_data;
                })
                ->tooltip(function (Student $record) {
                    if (! $record->completed_data) {
                        return '❌ لا يمكن إضافة دخولية: بيانات الطالب غير مكتملة';
                    }

                    return 'إضافة دخولية جديدة';
                })
                ->hidden($isObserver),
            ActionGroup::make([Tables\Actions\ViewAction::make(), Tables\Actions\EditAction::make()->hidden($isObserver), Tables\Actions\DeleteAction::make()->hidden($isObserver)])->icon('heroicon-m-ellipsis-horizontal'),
        ];
    }

    public static function getRelations(): array
    {
        return [RelationManagers\EntriesRelationManager::class, RelationManagers\EducationsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudents::route('/'),
            'create' => Pages\CreateStudent::route('/create'),
            'edit' => Pages\EditStudent::route('/{record}/edit'),
            'view' => Pages\ViewStudent::route('/{record}'),
        ];
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['id'] = Str::uuid()->toString();

        if (! isset($data['skip_national_number'])) {
            $data['skip_national_number'] = false;
        }

        return $data;
    }

    public static function canCreate(): bool
    {
        return (auth()->user()->role === Roles::CenterAdmin() || auth()->user()->role === Roles::GeneralAdmin()) && auth()->user()->role !== Roles::Observer();
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->role !== Roles::MealAdmin() && auth()->user()->role !== Roles::Observer();
    }

    public static function canDelete(Model $record): bool
    {
        return (auth()->user()->role === Roles::CenterAdmin() || auth()->user()->role === Roles::GeneralAdmin()) && auth()->user()->role !== Roles::Observer();
    }
}
