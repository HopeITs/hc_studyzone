<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Actions\Action;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\StudentResource\RelationManagers\EntriesRelationManager;
use App\Filament\Resources\StudentResource\RelationManagers\EducationsRelationManager;
use App\Filament\Resources\StudentResource\Pages\ListStudents;
use App\Filament\Resources\StudentResource\Pages\CreateStudent;
use App\Filament\Resources\StudentResource\Pages\EditStudent;
use App\Filament\Resources\StudentResource\Pages\ViewStudent;
use App\Filament\Resources\StudentResource\Pages\CreateStudentEntry;
use App\Models\ServicePrice;
use App\Models\EntryService;
use App\Enums\Roles;
use App\Filament\Exports\StudentExporter;
use App\Filament\Resources\StudentResource\Pages;
use App\Filament\Resources\StudentResource\RelationManagers;
use App\Models\City;
use App\Models\Education;
use App\Models\Street;
use App\Models\Student;
use App\Models\StudentEducation;
use App\Models\StudentEntry;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use UnitEnum;

// And your models

class StudentResource extends Resource
{
    use InteractsWithPageFilters;

    protected static ?string $model = Student::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return __('general.labels.students');
    }

    public static function getModelLabel(): string
    {
        return __('general.labels.student');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.labels.students');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['educations','firstEntry.center']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Wizard::make([self::personalAndContactInformationStep(), self::educationStep()])->columnSpanFull()]);
    }

    protected static function personalAndContactInformationStep(): Step
    {
        return Step::make(__('general.labels.personal_information').' & '.__('general.labels.contact_information'))
            ->schema(
                fn (?Student $record) => [
                    Placeholder::make('data_status_notice')
                        ->label(__('general.labels.notice'))
                        ->content(function (?Student $record) {
                            if (! $record) {
                                return '';
                            }

                            if (! $record->completed_data) {
                                $errors = $record->getEntryValidationErrors();
                                $message = '⚠️ **'.__('general.messages.complete_student_before_entry')."**\n\n**".__('general.messages.missing_fields').'**';

                                if (! empty($errors)) {
                                    $message .= "\n";
                                    foreach ($errors as $error) {
                                        $message .= "• {$error}\n";
                                    }
                                }

                                return $message;
                            }

                            return '✅ **'.__('general.messages.all_data_completed').'**';
                        })
                        ->hidden(fn (?Student $record): bool => $record === null)
                        ->columnSpanFull()
                        ->extraAttributes(['class' => 'bg-gray-50 dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 whitespace-pre-line']),

                    TextInput::make('firstname')->label(__('general.labels.firstname'))->required()->maxLength(255)->columnSpan(1),
                    TextInput::make('lastname')->label(__('general.labels.lastname'))->required()->maxLength(255)->columnSpan(1),
                    TextInput::make('father_name')->label(__('general.labels.father_name'))->required()->maxLength(255)->columnSpan(1),

                    Grid::make(3)->schema([
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

                        Grid::make(2)
                            ->columnSpan(2)
                            ->schema([
                                Checkbox::make('is_child')
                                    ->label(__('general.labels.is_child'))
                                    ->default(false)
                                    ->live()
                                    ->afterStateHydrated(function ($set, $get, $state, $livewire) {
                                        $record = $livewire->record ?? null;
                                        if ($record && is_null($record->national_number) && ! $record->skip_national_number) {
                                            $set('is_child', true);
                                        }
                                    }),

                                Checkbox::make('skip_national_number')
                                    ->label(__('general.labels.skip_national_number'))
                                    ->helperText(__('general.labels.skip_national_number_help'))
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

                    Textarea::make('skip_national_number_reason')->label(__('general.labels.skip_national_number_reason'))->placeholder(__('general.labels.skip_national_number_placeholder'))->required(fn (Get $get) => $get('skip_national_number') === true)->visible(fn (Get $get) => in_array(auth()->user()->role, [Roles::GeneralAdmin(), Roles::CityAdmin()]) && $get('skip_national_number') === true)->columnSpan(3)->maxLength(500),

                    TextInput::make('national_number')
                        ->label(fn (Get $get) => $get('nationality') === 'syrian' ? __('general.labels.national_number') : __('general.labels.foreign_id_number'))
                        ->required(fn (Get $get) => ! $get('is_child') && ! $get('skip_national_number'))
                        ->disabled(fn (Get $get) => $get('is_child') || $get('skip_national_number'))
                        ->mask(fn (Get $get): ?string => $get('nationality') === 'syrian' ? '99999999999' : null)
                        ->inputMode(fn (Get $get): ?string => $get('nationality') === 'syrian' ? 'numeric' : null)
                        ->rule('numeric', fn (Get $get): bool => $get('nationality') === 'syrian')
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
                                        ->title(__('general.messages.student_exists_in_city', ['name' => $student->firstname, 'city' => $student->street->city->name]))
                                        ->danger()
                                        ->persistent()
                                        ->actions([Action::make('view')->button()->label(__('general.messages.view_student'))->url(route('filament.admin.resources.students.view', $student), shouldOpenInNewTab: true)])
                                        ->send();

                                    $fail(__('general.messages.student_exists_in_city', ['name' => $student->firstname, 'city' => $student->street->city->name]));
                                }
                            },
                        ])
                        ->validationMessages([
                            'digits' => __('general.messages.national_number_digits'),
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
                        ->placeholder(fn (Get $get): string => empty($get('city_id')) ? __('general.labels.first_select_city') : __('general.labels.select_option'))
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
                                    $fail(__('general.messages.syrian_phone'));
                                }
                            },
                        ])
                        ->validationMessages([
                            'required' => __('general.messages.phone_required'),
                        ]),
                    TextInput::make('whatsapp')
                        ->label(__('general.labels.whatsapp'))
                        ->rules([
                            fn (Get $get) => function (string $attribute, $value, Closure $fail) use ($get) {
                                if ($value && $get('nationality') === 'syrian' && ! preg_match('/^09\d{8}$/', $value)) {
                                    $fail(__('general.messages.syrian_whatsapp'));
                                }
                            },
                        ]),
                    TextInput::make('email')->label(__('general.labels.email'))->unique(ignoreRecord: true),
                    TextInput::make('address')->label(__('general.labels.address'))->maxLength(255),
                ],
            )
            ->columns(3);
    }

    protected static function educationStep(): Step
    {
        return Step::make(__('general.labels.education'))
            ->schema([
                Repeater::make('educations')
                    ->relationship('studentEducations')
                    ->label(__('general.labels.educations'))
                    ->schema([
                        Select::make('degree')
                            ->label(__('general.labels.degree'))
                            ->options(__('general.edu-degrees'))
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('education_id', null))
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
                                    ? Education::where('degree', $degree)->pluck('name', 'id')
                                    : Education::pluck('name', 'id');
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

                        DatePicker::make('start_date')
                            ->label(__('general.labels.start_date'))
                            ->default(now())
                            ->required()
                            ->displayFormat('Y-m-d'),

                        DatePicker::make('end_date')
                            ->label(__('general.labels.end_date'))
                            ->nullable()
                            ->after('start_date')
                            ->helperText(__('general.messages.ongoing_study_help'))
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

                                $timelineItems = [];
                                $activeItems = [];
                                $activeDegrees = [];
                                foreach ($value as $index => $item) {

                                    if (! isset($item['degree']) || ! isset($item['level']) || ! isset($item['education_id']) || ! isset($item['start_date'])) {
                                        continue;
                                    }

                                    if (isset($item['end_date']) && Carbon::parse($item['end_date'])->isFuture()) {
                                        Notification::make()->danger()->title(__('general.messages.error'))->body(__('general.messages.end_date_future'))->send();
                                        $fail(__('general.messages.end_date_future'));

                                        return;
                                    }

                                    $degreeValue = $item['degree'];
                                    if ($degreeValue instanceof UnitEnum) {
                                        $degreeValue = $degreeValue->value;
                                    }
                                    $degreeValue = (int) $degreeValue;

                                    $degreeLevelKey = (string) $degreeValue.'_'.$item['level'].'_'.$item['education_id'];
                                    if (in_array($degreeLevelKey, $seenCombinations)) {
                                        Notification::make()->danger()->title(__('general.messages.error'))->body(__('general.messages.duplicate_degree_level'))->send();
                                        $fail(__('general.messages.duplicate_degree_level'));

                                        return;
                                    }
                                    $seenCombinations[] = $degreeLevelKey;

                                    $eduId = $item['education_id'];
                                    if (! isset($groupedByEducation[$eduId])) {
                                        $groupedByEducation[$eduId] = [];
                                    }

                                    $currentStart = Carbon::parse($item['start_date']);
                                    $currentEnd = isset($item['end_date']) ? Carbon::parse($item['end_date']) : null;

                                    foreach ($groupedByEducation[$eduId] as $existingItem) {
                                        $existStart = Carbon::parse($existingItem['start_date']);
                                        $existEnd = isset($existingItem['end_date']) ? Carbon::parse($existingItem['end_date']) : now()->addYears(100);

                                        $checkEnd = $currentEnd ?? now()->addYears(100);

                                        if ($currentStart->lt($existEnd) && $existStart->lt($checkEnd)) {
                                            Notification::make()->danger()->title(__('general.messages.error'))->body(__('general.messages.date_overlap'))->send();
                                            $fail(__('general.messages.date_overlap'));

                                            return;
                                        }
                                    }
                                    $groupedByEducation[$eduId][] = $item;

                                    if (! isset($item['end_date']) || empty($item['end_date'])) {
                                        $activeDegrees[] = (int) $degreeValue;
                                    }

                                    $timelineItems[] = [
                                        'start' => $currentStart,
                                        'end' => $currentEnd,
                                        'degree' => $degreeValue,
                                        'name' => __("general.edu-degrees.{$degreeValue}") ?? "Degree {$degreeValue}",
                                    ];

                                    if (! $currentEnd) {
                                        $activeItems[] = [
                                            'degree' => $degreeValue,
                                            'start_date' => $currentStart,
                                        ];
                                    }
                                }
                                usort($timelineItems, function ($a, $b) {
                                    return $a['start']->lt($b['start']) ? -1 : 1;
                                });

                                for ($i = 1; $i < count($timelineItems); $i++) {
                                    $prev = $timelineItems[$i - 1];
                                    $curr = $timelineItems[$i];

                                    if ($prev['end']) {
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

                                $activeCount = count($activeDegrees);
                                if ($activeCount < 1) {
                                    Notification::make()
                                        ->danger()
                                        ->title('خطأ في توحيد البيانات')
                                        ->body('يجب أن تكون هناك على الأقل دراسة واحدة نشطة (بدون تاريخ نهاية).')
                                        ->send();
                                    $fail('يجب أن تكون هناك على الأقل دراسة واحدة نشطة (بدون تاريخ نهاية).');

                                    return;
                                }

                                if ($activeCount > 2) {
                                    Notification::make()
                                        ->danger()
                                        ->title('خطأ في عدد الدراسات النشطة')
                                        ->body('لا يمكن أن يكون هناك أكثر من دراستين نشطتين في نفس الوقت.')
                                        ->send();
                                    $fail('لا يمكن أن يكون هناك أكثر من دراستين نشطتين في نفس الوقت.');

                                    return;
                                }
                                if ($activeCount === 2) {
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
            ->recordActions(self::tableActions())
            ->toolbarActions([
                ExportBulkAction::make()
                    ->exporter(StudentExporter::class)
                    ->formats([ExportFormat::Xlsx]),
                BulkActionGroup::make([DeleteBulkAction::make()->hidden(fn () => auth()->user()->role === Roles::Observer())])->hidden(fn () => auth()->user()->role === Roles::Observer()),
            ])
            ->paginated([20, 50, 100, 200]);
    }

    protected static function tableColumns(): array
    {
        return [
            TextColumn::make('firstname')
                ->label(__('general.labels.full_name'))
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
            IconColumn::make('completed_data')->label(__('general.labels.data_status'))->boolean()->trueIcon('heroicon-o-check-circle')->falseIcon('heroicon-o-x-circle')->trueColor('success')->falseColor('danger')->sortable()->tooltip(fn ($record) => $record->completed_data ? __('general.messages.completed_data_tooltip') : __('general.messages.incomplete_data_tooltip')),
            TextColumn::make('year_of_birth')->label(__('general.labels.date_of_birth'))->sortable()->toggleable(),
            TextColumn::make('nationality')->label(__('general.labels.nationality'))->sortable()->searchable()->toggleable(),
            TextColumn::make('national_number')->label(__('general.labels.national_number_or_residency'))->sortable()->searchable()->toggleable(),
            TextColumn::make('skip_national_number')->label(__('general.labels.skipped'))->badge()->formatStateUsing(fn ($state) => $state ? __('general.labels.yes') : __('general.labels.no'))->color(fn ($state) => $state ? 'warning' : 'gray')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('skip_national_number_reason')->label(__('general.labels.skip_national_number_reason'))->limit(30)->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('phone')->label(__('general.labels.phone'))->sortable()->searchable()->toggleable(),
            TextColumn::make('email')->label(__('general.labels.email'))->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('entries_count')->counts('entries')->label(__('general.labels.entries'))->sortable()->toggleable(),
            TextColumn::make('whatsapp')->label(__('general.labels.whatsapp'))->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('street.name')->label(__('general.labels.street'))->toggleable()->sortable()->searchable(),
            TextColumn::make('center')
                ->label(__('general.labels.center'))
                ->toggleable()
            ,
            TextColumn::make('educations')
                ->label(__('general.labels.education'))
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
                            StudentEducation::select('education_id')
                                ->whereColumn('student_id', 'students.id')
                                ->orderBy('start_date', 'desc')
                                ->limit(1),
                            $direction
                        );
                    },
                )
                ->toggleable(),
            TextColumn::make('education_level')
                ->label(__('general.labels.education_level'))
                ->state(function (Student $record) {
                    return $record->studentEducations()
                        ->orderBy('start_date', 'desc')
                        ->first()?->level;
                })
                ->formatStateUsing(fn ($state) => $state !== null ? (__('general.edu-levels.'.$state)) : '-')
                ->sortable(query: function (Builder $query, string $direction): Builder {
                    return $query->orderBy(
                        StudentEducation::select('level')
                            ->whereColumn('student_id', 'students.id')
                            ->orderBy('start_date', 'desc')
                            ->limit(1),
                        $direction
                    );
                })
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->whereHas('studentEducations', function (Builder $studentQuery) use ($search) {
                        $studentQuery->where('level', 'like', "%{$search}%");
                    });
                })
                ->toggleable(),
            TextColumn::make('street.city.name')->label(__('general.labels.city'))->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('gender')
                ->label(__('general.labels.gender'))
                ->badge()
                ->sortable()
                ->formatStateUsing(function ($state) {
                    Log::info('Gender value: '.gettype($state).' - '.json_encode($state));

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
                ->label(__('general.labels.religion'))
                ->badge()
                ->sortable()
                ->formatStateUsing(function ($state) {
                    Log::info('Religion value: '.gettype($state).' - '.json_encode($state));

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
            TextColumn::make('created_at')->label(__('general.labels.created_at'))->formatStateUsing(fn (Model $record) => $record->created_at->format('Y-m-d'))->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    protected static function qrCodeModalContent(Student $record)
    {
        $url = url("students/{$record->id}/entry");
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
            $streetsArr = Street::where('city_id', $cityId)->pluck('id')->toArray();
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
            TernaryFilter::make('completed_data')->label(__('general.labels.data_status'))->placeholder(__('general.labels.all'))->trueLabel(__('general.labels.yes').' - '.__('general.labels.completed_data'))->falseLabel(__('general.labels.no').' - '.__('general.labels.incomplete_data'))->queries(true: fn (Builder $query) => $query->where('completed_data', true), false: fn (Builder $query) => $query->where('completed_data', false), blank: fn (Builder $query) => $query),
            SelectFilter::make('city')
                ->relationship('street.city', 'name')
                ->label(__('general.labels.city'))
                ->preload()
                ->multiple()
                ->options(City::all()->pluck('name', 'id'))
                ->searchable(),
            SelectFilter::make('street_id')->label(__('general.labels.street'))->preload()->options($streets)->searchable(),
            SelectFilter::make('center')
             ->relationship('firstEntry.center', 'name')
                ->label(__('general.labels.center'))
                ->preload()

                ->searchable(),
            Filter::make('created_at')
                ->columns(2)
                ->schema([DatePicker::make('created_from')->label(__('general.labels.created_from')), DatePicker::make('created_until')->label(__('general.labels.created_until'))])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when($data['created_from'], fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))->when($data['created_until'], fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date));
                }),
            SelectFilter::make('educations')->label(__('general.labels.education_plural'))->relationship('educations', 'name')->searchable()->multiple()->preload(),
            SelectFilter::make('degree')
                ->label(__('general.labels.degree'))
                ->options(__('general.edu-degrees'))
                ->multiple()
                ->searchable()
                ->query(function (Builder $query, array $data): Builder {
                    if (empty($data['values'])) {
                        return $query;
                    }

                    return $query->whereHas('educations', function (Builder $q) use ($data) {
                        $q->whereIn('degree', $data['values']);
                    });
                }),
            SelectFilter::make('education_level')
                ->label(__('general.labels.education_level'))
                ->options(__('general.edu-levels'))
                ->multiple()
                ->searchable()
                ->query(function (Builder $query, array $data): Builder {
                    if (empty($data['values'])) {
                        return $query;
                    }

                    return $query->whereHas('studentEducations', function (Builder $studentQuery) use ($data) {
                        $studentQuery->whereIn('level', $data['values']);
                    });
                })
                ->preload(),
            SelectFilter::make('gender')->label(__('general.labels.gender'))->options(__('general.genders')),
            SelectFilter::make('religion')->label(__('general.labels.religion'))->options(__('general.religions')),
            TernaryFilter::make('national_number')->label(__('general.labels.national_number_or_residency'))->placeholder(__('general.labels.all'))->trueLabel(__('general.labels.exists'))->falseLabel(__('general.labels.not_exists'))->queries(true: fn (Builder $query) => $query->whereNotNull('national_number'), false: fn (Builder $query) => $query->whereNull('national_number'), blank: fn (Builder $query) => $query),
            TernaryFilter::make('skip_national_number')->label(__('general.labels.skip_national_number'))->placeholder(__('general.labels.all'))->trueLabel(__('general.labels.yes'))->falseLabel(__('general.labels.no')),
        ];
    }

    protected static function tableActions(): array
    {
        $isObserver = auth()->user()->role === Roles::Observer();

        return [
            Action::make('view_qr_code')->label(__('general.labels.view_qr_code'))->icon('heroicon-o-qr-code')->modalHeading(__('general.labels.qr_code_modal_heading'))->modalContent(fn (Student $record) => self::qrCodeModalContent($record))->modalCancelAction(false)->modalSubmitAction(false)->hidden($isObserver),
            Action::make('create-entry')
                ->label(__('general.labels.entry'))
                ->icon('heroicon-o-document-text')
                ->url(fn (Student $record) => static::getUrl('create-entry', ['record' => $record]))
                ->disabled(fn (Student $record) => ! $record->completed_data)
                ->tooltip(function (Student $record) {
                    if (! $record->completed_data) {
                        return '❌ لا يمكن إضافة دخولية: بيانات الطالب غير مكتملة';
                    }

                    return 'إضافة دخولية جديدة';
                })
                ->hidden($isObserver),

            ActionGroup::make([ViewAction::make(), EditAction::make()->hidden($isObserver), DeleteAction::make()->hidden($isObserver)])->icon('heroicon-m-ellipsis-horizontal'),
        ];
    }

    public static function getRelations(): array
    {
        return [EntriesRelationManager::class, EducationsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudents::route('/'),
            'create' => CreateStudent::route('/create'),
            'edit' => EditStudent::route('/{record}/edit'),
            'view' => ViewStudent::route('/{record}'),
            'create-entry' => CreateStudentEntry::route('/{record}/entry'),


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

    protected static function getCenterId(callable $get): ?int
    {
        return $get('center_id') ?? auth()->user()->center_id;
    }

    protected static function applyPriceConstraints($query, int $centerId): mixed
    {
        return $query
            ->where('center_id', $centerId)
            ->where('is_active', true)
            ->where('start_date', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            });
    }

    protected static function getCurrentPrice(int $serviceId, int $centerId): int
    {
        return ServicePrice::where('service_id', $serviceId)
            ->where('center_id', $centerId)
            ->where('is_active', true)
            ->where('start_date', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            })
            ->latest('start_date')
            ->value('price') ?? 0;
    }

    protected static function updateServicePrice($serviceId, callable $set, callable $get): void
    {
        $centerId = self::getCenterId($get);

        if ($serviceId && $centerId) {
            $price = self::getCurrentPrice($serviceId, $centerId);
            $set('price', $price);
            $set('amount', 1);
            $set('subtotal', $price);
        } else {
            $set('price', 0);
            $set('amount', 1);
            $set('subtotal', 0);
        }
    }

    protected static function calculateTotal(callable $get, callable $set): void
    {
        $total = self::calculateTotalFromServices($get);
        $set('total_amount', $total);
    }

    protected static function calculateTotalFromServices(callable $get): int
    {
        $services = $get('services') ?? [];
        $total = 0;

        foreach ($services as $service) {
            $total += (int) ($service['subtotal'] ?? 0);
        }

        return $total;
    }

    protected static function handleEntryCreation(array $data, Student $record): void
    {
        $services = $data['services'] ?? [];
        $totalAmount = $data['total_amount'] ?? 0;
        $centerId = $data['center_id'] ?? auth()->user()->center_id;

        $entry = StudentEntry::create([
            'student_id' => $record->id,
            'user_id' => Auth::id(),
            'center_id' => $centerId,
            'entry_type' => $data['entry_type'],
            'entry_time' => $data['entry_time'],
            'total_amount' => $totalAmount,
        ]);

        foreach ($services as $serviceData) {
            $serviceId = $serviceData['service_id'];
            $amount = $serviceData['amount'] ?? 1;
            $price = self::getCurrentPrice($serviceId, $centerId);
            $total = $price * $amount;

            EntryService::create([
                'entry_id' => $entry->id,
                'service_id' => $serviceId,
                'amount' => $amount,
                'total' => $total,
            ]);
        }
    }
}
