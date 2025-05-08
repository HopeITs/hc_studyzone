<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EntryResource\Pages;
use App\Models\StudentEntry;
use Filament\Forms\Components\{DateTimePicker, Select, Toggle};
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\{CheckboxColumn, TextColumn};
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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

        if ($user->hasRole('general_admin')) {
            return parent::getEloquentQuery();
        }

        if ($user->hasRole('city_admin')) {
            return parent::getEloquentQuery()->whereHas('center', function ($query) use ($user) {
                $query->where('city_id', $user->city_id);
            });
        }

        if ($user->hasRole('center_admin')) {
            return parent::getEloquentQuery()->where('center_id', $user->center_id);
        }

        return parent::getEloquentQuery()->whereRaw('1 = 0');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('student_id')
                ->relationship('student', 'firstname')
                ->label('الطالب')
                ->searchable()
                ->required(),

            Select::make('entry_type')
                ->label('نوع الدخول')
                ->options(__('general.entry-types'))
                ->required(),

            DateTimePicker::make('entry_time')
                ->label('تاريخ الدخول')
                ->required(),

            Toggle::make('use_printer')->label('خدمة طباعة'),
            Toggle::make('use_mecrator')->label('خدمة ميكراتور'),
            Toggle::make('use_drawer')->label('خدمة مرسم'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('اسم الطالب')
                    ->formatStateUsing(fn($record) => $record->student->firstname . ' ' . $record->student->father_name . ' ' . $record->student->lastname)
                    ->searchable(),

                TextColumn::make('entry_type')
                    ->label('نوع الدخول')
                    ->formatStateUsing(fn($state) => __('general.entry-types.' . $state)),

                TextColumn::make('entry_time')
                    ->label('تاريخ الدخول')
                    ->dateTime('Y-m-d H:i'),

                TextColumn::make('center.city.name')
                    ->label('المدينة')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('center.name')
                    ->label('المركز')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                CheckboxColumn::make('use_printer')->label('طباعة'),
                CheckboxColumn::make('use_mecrator')->label('ميكراتور'),
                CheckboxColumn::make('use_drawer')->label('مرسم'),
            ])
            ->filters([
                Filter::make('entry_time')
                    ->label('التاريخ')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('من'),
                        \Filament\Forms\Components\DatePicker::make('to')->label('إلى'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn($q, $date) => $q->whereDate('entry_time', '>=', $date))
                            ->when($data['to'], fn($q, $date) => $q->whereDate('entry_time', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEntries::route('/'),
            'create' => Pages\CreateEntry::route('/create'),
            'edit' => Pages\EditEntry::route('/{record}/edit'),
        ];
    }
}
