<?php

namespace App\Filament\Resources;

use App\Enums\Gender;
use App\Enums\Roles;
use App\Filament\Exports\StudentExporter;
use App\Filament\Resources\StudentResource\Pages;
use App\Filament\Resources\StudentResource\RelationManagers;
use App\Models\{City, Education, Street, Student, StudentEntry};

use Filament\Forms\Components\{Checkbox, DatePicker, DateTimePicker, Fieldset, Select, Textarea, TextInput, Toggle, Wizard};
use Closure;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\{Action, ActionGroup, ExportAction, ExportBulkAction};
use Filament\Tables\Columns\{CheckboxColumn, TextColumn};
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Filament\Notifications\Actions\Action as NotificationAction;
use App\Enums\EduDegree;

use Livewire\Component as Livewire;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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
        return Wizard\Step::make(__('general.labels.personal_information') . ' & ' . __('general.labels.contact_information'))
            ->schema(
                fn(?Student $record) => [
                    TextInput::make('firstname')->label(__('general.labels.firstname'))->required()->maxLength(255)->columnSpan(1),
                    TextInput::make('lastname')->label(__('general.labels.lastname'))->required()->maxLength(255)->columnSpan(1),
                    TextInput::make('father_name')->label(__('general.labels.father_name'))->required()->maxLength(255)->columnSpan(1),
                    TextInput::make('national_number')
                        ->label(__('general.labels.national_number'))
                        ->required(fn(Get $get) => !$get('is_child'))
                        ->disabled(fn(Get $get) => $get('is_child'))
                        ->rules([
                            fn() => function (string $attribute, $value, Closure $fail) use ($record) {
                                $student = Student::where('national_number', $value)->first();
                                if ($student && (!$record || $student->id !== $record->id)) {
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
                        ->columnSpan(2),
                    Checkbox::make('is_child')
                        ->label('قاصر')
                        ->default(false)
                        ->reactive()
                        ->afterStateHydrated(function ($set, $get, $state, $livewire) {
                            $record = $livewire->record ?? null;
                            if ($record && is_null($record->national_number)) {
                                $set('is_child', true);
                            }
                        }),
                    Select::make('gender')->label(__('general.labels.gender'))->options(__('general.genders'))->required()->columnSpan(1),
                    Select::make('religion')->label(__('general.labels.religion'))->options(__('general.religions'))->default(0)->columnSpan(1),
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
                        ->afterStateUpdated(fn($state, callable $set) => $set('street_id', null)),
                    Select::make('street_id')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->label(__('general.labels.street'))
                        ->placeholder(fn(Get $get): string => empty($get('city_id')) ? 'First select City' : 'Select an option')
                        ->options(function (?Student $record, Get $get, Set $set) {
                            if (!empty($record) && empty($get('city_id'))) {
                                $set('city_id', $record->street->city_id ?? '');
                                $set('street_id', $record->street_id);
                            }
                            return Street::where('city_id', $get('city_id'))->pluck('name', 'id');
                        }),
                    TextInput::make('phone')->label(__('general.labels.phone'))->required(),
                    TextInput::make('whatsapp')->label(__('general.labels.whatsapp')),
                    TextInput::make('email')->label(__('general.labels.email'))->unique(ignoreRecord: true),
                    TextInput::make('address')->label(__('general.labels.address'))->maxLength(255),
                ],
            )
            ->columns(3);
    }

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
                                return $degree ? \App\Models\Education::where('degree', $degree)->pluck('name', 'id') : \App\Models\Education::pluck('name', 'id');
                            })
                            ->searchable()
                            ->required(),

                        Select::make('level')->label(__('general.labels.education_level'))->options(__('general.edu-levels'))->required(),

                        Select::make('type')->label(__('general.labels.education_type'))->options(__('general.edu-types'))->required(),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->addActionLabel(__('general.labels.add_education'))
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => self::filterQueryByRole($query))
            ->columns(self::tableColumns())
            ->filtersFormColumns(2)
            ->filters(self::tableFilters())
            ->recordUrl(function (Student $record): string {
                if (auth()->user()->role === Roles::CenterAdmin()) {
                    return static::getUrl('edit', ['record' => $record]);
                }
                return static::getUrl('view', ['record' => $record]);
            })
            ->toggleColumnsTriggerAction(fn(Action $action) => $action->button()->label('Toggle columns'))
            ->actions(self::tableActions())
            ->bulkActions([
                ExportBulkAction::make()
                    ->exporter(StudentExporter::class)
                    ->formats([ExportFormat::Xlsx]),
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ])
            ->paginated([20, 50, 100, 200]);
    }

    protected static function tableColumns(): array
    {
        return [
            TextColumn::make('firstname')
                ->label('Full name')
                ->formatStateUsing(fn(Model $record) => $record->firstname . ' ' . $record->father_name . ' ' . $record->lastname)
                ->sortable(['firstname', 'lastname'])
                ->searchable(
                    query: function (Builder $query, string $search): Builder {
                        return $query
                            ->where('firstname', 'like', "%{$search}%")
                            ->orWhere('lastname', 'like', "%{$search}%")
                            ->orWhere('father_name', 'like', "%{$search}%");
                    },
                ),
            TextColumn::make('year_of_birth')->label('Date of Birth')->sortable()->toggleable(),
            TextColumn::make('national_number')->label('National number')->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('phone')->label('Phone')->sortable()->searchable()->toggleable(),
            TextColumn::make('email')->label('Email')->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('entries_count')->counts('entries')->label('Entries')->sortable()->toggleable(),
            TextColumn::make('whatsapp')->label('Whatsapp')->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('street.name')->label('Street')->toggleable()->sortable()->searchable(),
            TextColumn::make('educations')
                ->label('Education')
                ->formatStateUsing(function (Model $record) {
                    return $record->educations
                        ->map(function ($education) {
                            $translatedDegree = $education->degree ? __('general.edu-degrees')[$education->degree->value] ?? $education->degree->name : 'No Degree';
                            return $education->name . ' - ' . $translatedDegree;
                        })
                        ->implode(', ');
                })
                ->searchable(
                    query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('educations', function (Builder $studentQuery) use ($search) {
                            $studentQuery->where('name', 'like', "%{$search}%");
                        });
                    },
                )
                ->sortable(
                    query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            \App\Models\Education::select('name')
                                ->join('student_educations', 'educations.id', '=', 'student_educations.education_id')
                                ->whereColumn('student_educations.student_id', 'students.id')
                                ->orderBy('name', 'asc')
                                ->limit(1),
                            $direction,
                        );
                    },
                )
                ->toggleable(),
            TextColumn::make('street.city.name')->label('City')->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('gender')
                ->label('Gender')
                ->badge()
                ->formatStateUsing(fn(string $state) => __("general.genders.{$state}"))
                ->color(
                    fn(int $state): string => match ($state) {
                        Gender::Unknown() => 'warning',
                        Gender::Male() => 'info',
                        Gender::Female() => 'danger',
                    },
                )
                ->toggleable(isToggledHiddenByDefault: true),
            CheckboxColumn::make('is_student')->toggleable(isToggledHiddenByDefault: true),
            CheckboxColumn::make('is_employed')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('created_at')->label('Created at')->formatStateUsing(fn(Model $record) => $record->created_at->format('Y-m-d'))->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    protected static function qrCodeModalContent(Student $record)
    {
        $url = url("student/{$record->id}/entry");
        $localUrl = 'https://hope_studyzone.test/student/' . $record->id . '/entry';
        $onlineUrl = 'https://studyzone.sha8er.com/student/' . $record->id . '/entry';
        $qrCode = QrCode::size(200)->generate($url);
        return view('filament.qr-code-modal', [
            'uuid' => $record->id,
            'url' => $url,
            'offlineUrl' => $localUrl,
            'onlineUrl' => $onlineUrl,
            'qrCode' => $qrCode,
        ]);
    }

    protected static function filterQueryByRole(Builder $query): Builder
    {
        $user = Auth::user();
        if ($user->role === Roles::GeneralAdmin()) {
            return $query;
        } else {
            $cityId = $user->city_id;
            $streetsArr = \App\Models\Street::where('city_id', $cityId)->pluck('id')->toArray();
            $streetsArr[] = 0;
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
            SelectFilter::make('city')
                ->relationship('street.city', 'name')
                ->label('المدينة')
                ->preload()
                ->multiple()
                ->options(City::all()->pluck('name', 'id'))
                ->searchable(),
            SelectFilter::make('street_id')->label('الشارع')->preload()->options($streets)->searchable(),
            Filter::make('created_at')->columns(2)
                ->form([DatePicker::make('created_from')->label('تاريخ الإضافة من'), DatePicker::make('created_until')->label('تاريخ الإضافة حتى')])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when($data['created_from'], fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))->when($data['created_until'], fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date));
                }),
            SelectFilter::make('educations')->label('المؤهلات العلمية')->relationship('educations', 'name')->searchable()->multiple()->preload(),
            SelectFilter::make('gender')->label('الجنس')->options(__('general.genders')),
            SelectFilter::make('religion')->label('الديانة')->options(__('general.religions')),


        ];
    }

    protected static function tableActions(): array
    {
        return [
            Tables\Actions\Action::make('view_qr_code')->label(__('general.labels.view_qr_code'))->icon('heroicon-o-qr-code')->modalHeading(__('general.labels.qr_code_modal_heading'))->modalContent(fn(Student $record) => self::qrCodeModalContent($record))->modalCancelAction(false)->modalSubmitAction(false),
            Tables\Actions\Action::make('entry')
                ->label(__('general.labels.entry'))
                ->icon('heroicon-o-document-text')
                ->form([
                    Select::make('entry_type')->label(__('general.labels.entry_type'))->default(1)->options(__('general.entry-types'))->columnSpan(1)->required(),
                    DateTimePicker::make('entry_time')->label(__('general.labels.entry_time'))->default(now())->columnSpan(1)->required(),
                    Fieldset::make('Services')
                        ->label('خدمات')
                        ->schema([Checkbox::make('use_printer')->reactive()->label('خدمة طباعة')->default(false), Checkbox::make('use_drawer')->reactive()->label('خدمة مرسم')->default(false), Checkbox::make('use_mecrator')->reactive()->label('خدمة ميكراتور')->default(false)])
                        ->columns(3),
                ])
                ->modalHeading(__('general.labels.record_student_entry'))
                ->modalButton(__('general.labels.save_entry'))
                ->action(
                    fn(array $data, Student $record) => StudentEntry::create([
                        'student_id' => $record->id,
                        'user_id' => Auth::id(),
                        'center_id' => Auth::user()->center_id ?? 1,
                        'entry_type' => $data['entry_type'],
                        'entry_time' => $data['entry_time'],
                        'use_drawer' => $data['use_drawer'],
                        'use_printer' => $data['use_printer'],
                        'use_mecrator' => $data['use_mecrator'],
                    ]),
                )
                ->successNotificationTitle(__('general.labels.entry_recorded_successfully')),
            ActionGroup::make([Tables\Actions\ViewAction::make(), Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])->icon('heroicon-m-ellipsis-horizontal'),
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
        return $data;
    }

    public static function canCreate(): bool
    {
        return auth()->user()->role === Roles::CenterAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->role !== Roles::MealAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->role === Roles::CenterAdmin();
    }
}
