<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentResource\Pages;
use App\Filament\Resources\StudentResource\RelationManagers;
use App\Models\{City, Education, Street, Student, StudentEntry};
use Filament\Forms\Components\{DatePicker, Select, Textarea, TextInput, Toggle, Wizard};
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

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationSort = 6;

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
        return Wizard\Step::make('Personal Information')
            ->schema([
                TextInput::make('firstname')->label('First Name')->required()->maxLength(255),
                TextInput::make('lastname')->label('Last Name')->required()->maxLength(255),
                TextInput::make('father_name')->label("Father's Name")->required()->maxLength(255),
                TextInput::make('national_number')->label('National Number')->required()->unique(ignoreRecord: true),
                Select::make('gender')->label('Gender')->options(__('general.genders'))->required(),
                Select::make('religion')->label('Religion')->options(__('general.religions'))->default(0),
                DatePicker::make('dob')->label('Date of Birth'),
                Toggle::make('is_student'),
                Toggle::make('is_employed')->label('Is Employee'),
                Textarea::make('notes')->label('Notes')->maxLength(500)->columnSpan(2),
            ])
            ->columns(2);
    }

    protected static function contactInformationStep(): Wizard\Step
    {
        return Wizard\Step::make('Contact Information')
            ->schema([
                Select::make('street_id')
                    ->label('Street')
                    ->options(fn() => Street::where('city_id', auth()->user()->city_id)->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                TextInput::make('phone')->label('Phone')->required()->unique(ignoreRecord: true),
                TextInput::make('whatsapp')->label('Whatsapp')->unique(ignoreRecord: true),
                TextInput::make('email')->label('Email')->required()->unique(ignoreRecord: true),
                TextInput::make('address')->label('Address')->maxLength(255)->columnSpanFull(),
            ])
            ->columns(2);
    }

    protected static function educationStep(): Wizard\Step
    {
        return Wizard\Step::make('Education')
            ->schema([
                \Filament\Forms\Components\Repeater::make('educations')
                    ->relationship('studentEducations')
                    ->schema([
                        Select::make('education_id')
                            ->label('Education')
                            ->options(Education::all()->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('level')->label('Education Level')->options(__('general.edu-levels'))->required(),
                        Select::make('type')->label('Education Type')->options(__('general.edu-types'))->required(),
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
            ->actions(self::tableActions())
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    protected static function tableColumns(): array
    {
        return [
            TextColumn::make('firstname')->label('First Name')->sortable()->searchable(),
            TextColumn::make('lastname')->label('Last Name')->sortable()->searchable(),
            TextColumn::make('phone')->label('Phone')->sortable()->searchable(),
            TextColumn::make('street.city.name')->label('City')->sortable()->searchable(),
            TextColumn::make('gender')
                ->label('Gender')
                ->badge()
                ->formatStateUsing(fn(string $state) => __("general.genders.{$state}"))
                ->color(fn(string $state) => $state === '1' ? 'info' : 'danger'),
            CheckboxColumn::make('is_student'),

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
            SelectFilter::make('city')
                ->relationship('street.city', 'name')
                ->label('Filter by City')
                ->preload()
                ->options(City::all()->pluck('name', 'id'))
                ->searchable()
                ->visible(auth()->user()->hasRole('general_admin')),
            SelectFilter::make('street_id')
                ->label('Filter by Street')
                ->options(Street::whereIn('city_id', [auth()->user()->city_id] ?? City::all()->keys()->toArray())->pluck('name', 'id'))
                ->searchable(),
            SelectFilter::make('educations')
                ->label('Filter by Educations')
                ->relationship('educations', 'name')
                ->searchable()
                ->multiple()
                ->preload(),
            SelectFilter::make('gender')
                ->label('Filter by Gender')
                ->options(__('general.genders')),
        ];
    }

    protected static function tableActions(): array
    {
        return [
            Tables\Actions\Action::make('view_qr_code')
                ->label('View QR Code')
                ->icon('heroicon-o-qr-code')
                ->modalHeading('Student QR Code Details')
                ->modalContent(fn(Student $record) => self::qrCodeModalContent($record))
                ->modalSubmitAction(false),
            Action::make('entry')
                ->label('Entry')
                ->icon('heroicon-o-document-text')
                ->form([Select::make('entry_type')->label('Entry Type')->options(__('general.entry-types'))->required()])
                ->modalHeading('Record Student Entry')
                ->modalButton('Save Entry')
                ->action(
                    fn(array $data, Student $record) => StudentEntry::create([
                        'student_id' => $record->id,
                        'user_id' => Auth::id(),
                        'center_id' => Auth::user()->center_id ?? 1,
                        'entry_type' => $data['entry_type'],
                        'entry_time' => now(),
                    ])
                )
                ->successNotificationTitle('Entry Recorded Successfully'),
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
            RelationManagers\EducationsRelationManager::class,
            RelationManagers\EntriesRelationManager::class,
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
                ->modalCancelActionLabel('Close'),
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

    public static function canEdit(Model $record): bool
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
