<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentResource\Pages;
use App\Filament\Resources\StudentResource\RelationManagers;
use App\Models\City;
use App\Models\Education;
use App\Models\Street;
use App\Models\Student;
use App\Models\StudentEntry;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationSort = 6;
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    // Step 1: Personal Information
                    Wizard\Step::make('Personal Information')
                        ->schema([
                            TextInput::make('firstname')->label('First Name')->required()->maxLength(255),
                            TextInput::make('lastname')->label('Last Name')->required()->maxLength(255),
                            TextInput::make('father_name')->label("Father's Name")->required()->maxLength(255),
                            TextInput::make('national_number')
                                ->label('National Number')
                                ->required()
                                ->unique(ignoreRecord: true),
                            Select::make('gender')
                                ->label('Gender')
                                ->options(__('general.genders'))
                                ->required(),
                            Select::make('religion')
                                ->label('Religion')
                                ->options(__('general.religions'))
                                ->default(0),
                            DatePicker::make('year_of_birth'),
                            Toggle::make('is_student'),
                            Toggle::make('is_employee'),
                            Textarea::make('notes')->label('Notes')->maxLength(500)->columnSpan(2),
                        ])->columns(2),

                    // Step 2: Contact Information
                    Wizard\Step::make('Contact Information')
                        ->schema([


                            Select::make('street_id')
                                ->label('Street')
                                ->options(fn () => Street::where('city_id', auth()->user()->city_id)->pluck('name', 'id'))
                                ->searchable()
                                ->required(),

                            TextInput::make('phone')->label('Phone')->required()->unique(ignoreRecord: true),
                            TextInput::make('whatsapp')->label('Whatsapp')->unique(ignoreRecord: true),
                            TextInput::make('email')->label('Email')->required()->unique(ignoreRecord: true),
                            TextInput::make('address')->label('Address')->maxLength(255)->columnSpanFull(),
                        ])->columns(2),

                    // Step 3: Education Information (Using Repeater)
                    Wizard\Step::make('Education')
                        ->schema([
                            \Filament\Forms\Components\Repeater::make('educations')
                                ->relationship('studentEducations') // Many-to-many relationship
                                ->schema([
                                    Select::make('education_id')
                                        ->label('Education')
                                        ->options(Education::all()->pluck('name', 'id'))
                                        ->searchable()
                                        ->required(),


                                    Select::make('level')
                                        ->label('Education Level')
                                        ->options(__('general.edu-levels'))
                                        ->required(),

                                    Select::make('type')
                                        ->label('Education Type')
                                        ->options(__('general.edu-types'))
                                        ->required(),
                                ])
                                ->columns(2)
                                ->minItems(1)
                                ->addActionLabel('Add Education')
                                ->columnSpanFull(),
                        ])->columns(1),

                ])->columnSpanFull()
            ]);
    }


    public static function table(Table $table): Table
    {

        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = Auth::user();

                if ($user->hasRole('general_admin')) {
                    return $query; // ✅ General admin sees all students
                } elseif ($user->hasRole('city_admin') || $user->hasRole('center_admin')) {
                    return $query->whereIn('street_id', $user->city->streets->pluck('id')->toArray()); // ✅ City admin & Center admin see only their city's students
                }

                return $query->whereRaw('1 = 0'); // ✅ Blocks unauthorized users from seeing data
            })
            ->columns([
                TextColumn::make('firstname')->label('First Name')->sortable()->searchable(),
                TextColumn::make('lastname')->label('Last Name')->sortable()->searchable(),
                TextColumn::make('phone')->label('Phone')->sortable()->searchable(),

                TextColumn::make('street.city.name')->label('City')->sortable()->searchable(),
                TextColumn::make('gender')
                    ->label('Gender')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => __("general.genders.{$state}"))
                    ->color(fn(string $state): string => match ($state) {
                        '1' => 'info',
                        '2' => 'danger',


                    }),
                CheckboxColumn::make('is_student'),


            ])
            ->filters([
                // City filter (Only for General Admins)
                SelectFilter::make('city')
                    ->relationship('street.city', 'name')
                    ->label('Filter by City')
                    ->preload()
                    ->options(City::all()->pluck('name', 'id'))
                    ->searchable()
                    ->visible(auth()->user()->hasRole('general_admin')),

                // Street Filter (Depends on City for General Admins, Else User's City)
                SelectFilter::make('street_id')
                    ->label('Filter by Street')
                    ->options(Street::whereIn('city_id', [auth()->user()->city_id] ??  City::all()->keys()->toArray())->pluck('name', 'id'))
                    ->searchable(),


                // Education filter
                SelectFilter::make('educations')
                    ->label('Filter by Educations')
                    ->relationship('educations', 'name')
                    ->searchable()
                    ->multiple()
                    ->preload(),

                // Gender filter
                SelectFilter::make('gender')
                    ->label('Filter by Gender')
                    ->options(__('general.genders')),
            ])

            ->actions([
                Action::make('entry')
                    ->label('Entry')
                    ->icon('heroicon-o-document-text')
                    ->form([
                        Select::make('entry_type')
                            ->label('Entry Type')
                            ->options(__('general.entry-types'))
                            ->required(),
                    ])
                    ->modalHeading('Record Student Entry')
                    ->modalButton('Save Entry')
                    ->action(function (array $data, Student $record) {
                        $user = Auth::user();
                        StudentEntry::create([
                            'student_id' => $record->id,
                            'user_id' => $user->id,
                            'center_id' => $user->center_id ?? 1,
                            'entry_type' => $data['entry_type'],
                            'entry_time' => now(),
                        ]);
                    })
                    ->successNotificationTitle('Entry Recorded Successfully'),
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),

                ])->icon('heroicon-m-ellipsis-horizontal')

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
