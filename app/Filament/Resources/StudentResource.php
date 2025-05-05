<?php

namespace App\Filament\Resources;

use App\Enums\Gender;
use App\Filament\Resources\StudentResource\Pages;
use App\Filament\Resources\StudentResource\RelationManagers;
use App\Models\{City, Education, Street, Student, StudentEntry};

use Filament\Forms\Components\{Checkbox, DatePicker, DateTimePicker, Fieldset, Select, Textarea, TextInput, Toggle, Wizard};
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\{Action, ActionGroup};
use Filament\Tables\Columns\{CheckboxColumn, TextColumn};
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Spatie\SimpleExcel\SimpleExcelWriter;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationSort = 6;
    protected static ?string $label = 'طالب';
    protected static ?string $modelLabel = 'طالب';
    protected static ?string $navigationLabel = 'الطلاب';
    protected static ?string $pluralModelLabel = 'الطلاب';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Wizard::make([
                self::personalInformationStep(),
                self::contactInformationStep(),
                self::educationStep(),
            ])->columnSpanFull(),
        ]);
    }

    protected static function personalInformationStep(): Wizard\Step
    {
        return Wizard::Step::make(__('general.labels.personal_information'))
            ->schema([
                TextInput::make('firstname')->label(__('general.labels.firstname'))->required()->maxLength(255)->columnSpan(1),
                TextInput::make('lastname')->label(__('general.labels.lastname'))->required()->maxLength(255)->columnSpan(1),
                TextInput::make('father_name')->label(__('general.labels.father_name'))->required()->maxLength(255)->columnSpan(1),
                TextInput::make('national_number')
                    ->label(__('general.labels.national_number'))
                    ->required(fn(Get $get) => !$get('is_child'))
                    ->disabled(fn(Get $get) => $get('is_child'))
                    ->unique(ignoreRecord: true)
                    ->columnSpan(2),
                Checkbox::make('is_child')->reactive()->label('قاصر')->default(false),
                Select::make('gender')->label(__('general.labels.gender'))->options(__('general.genders'))->required()->columnSpan(1),
                Select::make('religion')->label(__('general.labels.religion'))->options(__('general.religions'))->default(0)->columnSpan(1),
                DatePicker::make('year_of_birth')->label(__('general.labels.date_of_birth'))->columnSpan(1),
                Toggle::make('is_student')->label(__('general.labels.is_student'))->columnSpan(1),
                Toggle::make('is_employed')->label(__('general.labels.is_employed'))->columnSpan(1),
                Textarea::make('notes')->label(__('general.labels.notes'))->maxLength(500)->columnSpan(3),
            ])
            ->columns(4);
    }

    protected static function contactInformationStep(): Wizard\Step
    {
        return Wizard::Step::make(__('general.labels.contact_information'))
            ->schema([
                Select::make('city_id')
                    ->label(__('general.labels.city'))
                    ->relationship('street.city', 'name')
                    ->searchable()
                    ->required()
                    ->preload()
                    ->live()
                    ->default(auth()->user()->city_id)
                    ->reactive()
                    ->afterStateUpdated(fn($state, callable $set) => $set('street_id', null)),
                Select::make('street_id')
                    ->label(__('general.labels.street'))
                    ->options(fn(Get $get) => Street::query()->where('city_id', $get('city_id'))->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                TextInput::make('phone')->label(__('general.labels.phone'))->required(),
                TextInput::make('whatsapp')->label(__('general.labels.whatsapp')),
                TextInput::make('email')->label(__('general.labels.email'))->unique(ignoreRecord: true),
                TextInput::make('address')->label(__('general.labels.address'))->maxLength(255),
            ])
            ->columns(2);
    }

    protected static function educationStep(): Wizard\Step
    {
        return Wizard::Step::make(__('general.labels.education'))
            ->schema([
                \Filament\Forms\Components\Repeater::make('educations')
                    ->relationship('studentEducations')
                    ->schema([
                        Select::make('education_id')->label(__('general.labels.education'))->options(Education::all()->pluck('name', 'id'))->searchable()->required(),
                        Select::make('level')->label(__('general.labels.education_level'))->options(__('general.edu-levels'))->required(),
                        Select::make('type')->label(__('general.labels.education_type'))->options(__('general.edu-types'))->required(),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->addActionLabel('Add Education')
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => self::filterQueryByRole($query))
            ->columns(self::tableColumns())
            ->filters(self::tableFilters())


            ->headerActions([
                Tables\Actions\Action::make('export_all')
                    ->label(__('Export to Excel'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        $fileName = 'students-' . now()->format('Y-m-d_H-i-s') . '.csv';
                        $students = Student::with(['street.city'])->get();

                        $writer = SimpleExcelWriter::create(storage_path("app/public/{$fileName}"))
                            ->addHeader(['Full Name', 'Gender', 'Phone', 'Street', 'City', 'Year of Birth']);

                        foreach ($students as $student) {
                            $writer->addRow([
                                $student->firstname . ' ' . $student->father_name . ' ' . $student->lastname,
                                __('general.genders.' . $student->gender),
                                $student->phone,
                                optional($student->street)->name,
                                optional($student->street->city)->name,
                                $student->year_of_birth,
                            ]);
                        }

                        $writer->close();

                        return response()->download(storage_path("app/public/{$fileName}"))->deleteFileAfterSend(true);
                    }),
            ])

            ->actions(self::tableActions())

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ])
            ]);
    }

    protected static function tableColumns(): array
    {
        return [
            TextColumn::make('firstname')->label('Full name')
                ->formatStateUsing(fn(Model $record) => $record->firstname . ' ' . $record->father_name . ' ' . $record->lastname)
                ->sortable(['first_name', 'last_name'])
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query
                        ->where('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%")
                        ->orWhere('father_name', 'like', "%{$search}%");
                }),
            TextColumn::make('year_of_birth')->label('Date of Birth') ->sortable()->toggleable(),
            TextColumn::make('phone')->label('Phone')->sortable()->searchable()->toggleable(),
            TextColumn::make('street.name')->label('Street')->toggleable()->sortable()->searchable(),
            TextColumn::make('street.city.name')->label('City')->visible(auth()->user()->hasRole('general_admin'))->sortable()->searchable(),
            TextColumn::make('gender')->label('Gender')->badge()
                ->formatStateUsing(fn(string $state) => __("general.genders.{$state}"))
                ->color(fn(int $state): string => match ($state) {
                    Gender::Unknown() => 'warning',
                    Gender::Male() => 'info',
                    Gender::Female() => 'danger',
                })->toggleable(),
            CheckboxColumn::make('is_student')->toggleable(),
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
        return $user->hasRole('general_admin')
            ? $query
            : ($user->hasRole(['city_admin', 'center_admin'])
                ? $query->whereIn('street_id', $user->city->streets->pluck('id')->toArray())
                : $query->whereRaw('1 = 0'));
    }

    protected static function tableFilters(): array
    {
        return [
            SelectFilter::make('city')->relationship('street.city', 'name')->label('Filter by City')->preload()->options(City::all()->pluck('name', 'id'))->searchable()->visible(auth()->user()->hasRole('general_admin')),
            SelectFilter::make('street_id')->label('Filter by Street')->options(Street::whereIn('city_id', [auth()->user()->city_id] ?? City::all()->keys()->toArray())->pluck('name', 'id'))->searchable(),
            SelectFilter::make('educations')->label('Filter by Educations')->relationship('educations', 'name')->searchable()->multiple()->preload(),
            SelectFilter::make('gender')->label('Filter by Gender')->options(__('general.genders')),
        ];
    }

    protected static function tableActions(): array
    {
        return [
            Tables\Actions\Action::make('view_qr_code')->label(__('general.labels.view_qr_code'))->icon('heroicon-o-qr-code')->modalHeading(__('general.labels.qr_code_modal_heading'))->modalContent(fn(Student $record) => self::qrCodeModalContent($record))->modalCancelAction(false)->modalSubmitAction(false),
            Tables\Actions\Action::make('entry')->label(__('general.labels.entry'))->icon('heroicon-o-document-text')
                ->form([
                    Select::make('entry_type')->label(__('general.labels.entry_type'))->default(1)->options(__('general.entry-types'))->columnSpan(1)->required(),
                    DateTimePicker::make('entry_time')->label(__('general.labels.entry_time'))->default(now())->columnSpan(1)->required(),
                    Fieldset::make('Services')->label('خدمات')->schema([
                        Checkbox::make('use_printer')->reactive()->label('خدمة طباعة')->default(false),
                        Checkbox::make('use_drawer')->reactive()->label('خدمة مرسم')->default(false),
                        Checkbox::make('use_mecrator')->reactive()->label('خدمة ميكراتور')->default(false),
                    ])->columns(3)
                ])
                ->modalHeading(__('general.labels.record_student_entry'))
                ->modalButton(__('general.labels.save_entry'))
                ->action(fn(array $data, Student $record) => StudentEntry::create([
                    'student_id' => $record->id,
                    'user_id' => Auth::id(),
                    'center_id' => Auth::user()->center_id ?? 1,
                    'entry_type' => $data['entry_type'],
                    'entry_time' => $data['entry_time'],
                    'use_drawer' => $data['use_drawer'],
                    'use_printer' => $data['use_printer'],
                    'use_mecrator' => $data['use_mecrator'],
                ]))
                ->successNotificationTitle(__('general.labels.entry_recorded_successfully')),
            ActionGroup::make([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])->icon('heroicon-m-ellipsis-horizontal'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EntriesRelationManager::class,
            RelationManagers\EducationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudents::route('/'),
            'create' => Pages\CreateStudent::route('/create'),
            'edit' => Pages\EditStudent::route('/{record}/edit'),
        ];
    }

    public static function getGlobalActions(): array
    {
        return [
            Action::make('scan:qrcode')
                ->label('Open Camera')
                ->icon('heroicon-o-camera')
                ->modalHeading('Scan QR Code')
                ->modalContent(fn() => view('filament.qr-code-scanner'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalId('scan-qr-code'),
        ];
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['id'] = Str::uuid()->toString();
        return $data;
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasRole('center_admin');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->can('manage_students');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->hasRole('center_admin');
    }
}
