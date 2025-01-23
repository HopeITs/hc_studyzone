<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentResource\Pages;
use App\Filament\Resources\StudentResource\RelationManagers;
use App\Models\Student;
use App\Models\StudentEntry;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('firstname')->label('First Name')->required()->maxLength(255),
                TextInput::make('lastname')->label('Last Name')->required()->maxLength(255),
                TextInput::make('father_name')->label("Father's Name")->required()->maxLength(255),

                Select::make('city_id')
                    ->label('City')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('national_number')
                    ->label('National Number')
                    ->required()
                    ->unique(ignoreRecord: true),

                TextInput::make('phone')->label('Phone')->required()->unique(ignoreRecord: true),
                TextInput::make('email')->label('Email')->required()->unique(ignoreRecord: true),

                Select::make('gender')
                    ->label('Gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                    ])
                    ->required(),

                Select::make('religion')
                    ->label('Religion')
                    ->options([
                        'christian' => 'Christian',
                        'muslim' => 'Muslim',
                        'unknown' => 'Unknown',
                    ])
                    ->default('unknown'),

                TextInput::make('address')->label('Address')->maxLength(255),

                Textarea::make('note')->label('Notes')->maxLength(500),
            ]);
    }

    public static function table(Table $table): Table
    {

        return $table
            ->columns([
                TextColumn::make('firstname')->label('First Name')->sortable()->searchable(),
                TextColumn::make('lastname')->label('Last Name')->sortable()->searchable(),
                TextColumn::make('national_number')->label('National Number')->sortable()->searchable(),
                TextColumn::make('phone')->label('Phone')->sortable()->searchable(),
                TextColumn::make('city.name')->label('City')->sortable()->searchable(),
                TextColumn::make('gender')->label('Gender')->sortable(),
            ])
            ->filters([
                SelectFilter::make('city')
                    ->label('Filter by City')
                    ->relationship('city', 'name')
                    ->searchable(),

                SelectFilter::make('gender')
                    ->label('Filter by Gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                    ]),
            ])
            ->actions([
                Action::make('entry')
                    ->label('Record Entry')
                    ->icon('heroicon-o-document-text')
                    ->form([
                        Select::make('entry_type')
                            ->label('Entry Type')
                            ->options([
                                'classic' => 'Classic',
                                'dentist' => 'Dentist',
                                'civil' => 'Civil',
                            ])
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
            //
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
}
