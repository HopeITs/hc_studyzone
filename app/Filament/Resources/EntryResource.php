<?php

namespace App\Filament\Resources;

use App\Enums\Roles;
use App\Filament\Resources\EntryResource\Pages;
use App\Filament\Resources\EntryResource\Widgets\EntryStats;
use App\Models\StudentEntry;
use Filament\Forms\Components\{Checkbox, DateTimePicker, Fieldset, Select, Toggle};
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\{CheckboxColumn, TextColumn};
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
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

        if ($user->role === Roles::GeneralAdmin()) {
            return parent::getEloquentQuery();
        }

        if ($user->role === Roles::CityAdmin()) {
            return parent::getEloquentQuery()->whereHas('center', function ($query) use ($user) {
                $query->where('city_id', $user->city_id);
            });
        }

        if ($user->role === Roles::CenterAdmin()) {
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
                ->preload()
                ->required(),
            Select::make('center_id')
                ->relationship('center', 'name')
                ->label('المركز')
                ->searchable()
                ->preload()
                ->required(),

            Select::make('entry_type')
                ->label(__('general.labels.entry_type'))->default(1)
                ->options(__('general.entry-types'))->columnSpan(1)
                ->required(),
            DateTimePicker::make('entry_time')->label(__('general.labels.entry_time'))
                  ->default(now())
                ->columnSpan(1)
                ->required(),
            Fieldset::make('Services')->label('خدمات')
                ->schema([
                    Checkbox::make('use_printer')->reactive()
                        ->label('خدمة طباعة')
                        ->default(false)
                    ,
                    Checkbox::make('use_drawer')->reactive()
                        ->label('خدمة مرسم')
                        ->default(false)
                    ,
                    Checkbox::make('use_mecrator')->reactive()
                        ->label('خدمة ميكراتور')
                        ->default(false)
                    ,
                ])->columns(3)


        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.id')
                    ->label('اسم الطالب')
                    ->formatStateUsing(fn(Model $record) => $record->student->firstname . ' ' . $record->student->father_name . ' ' . $record->student->lastname)
                    ->sortable(['firstname'])
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('student', function (Builder $studentQuery) use ($search) {
                            $studentQuery->where('firstname', 'like', "%{$search}%")
                                ->orWhere('lastname', 'like', "%{$search}%")
                                ->orWhere('father_name', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('entry_type')
                    ->label('نوع الدخول')
                    ->formatStateUsing(fn($state) => __('general.entry-types.' . $state)),

                TextColumn::make('student.educations')
                    ->label('المؤهل العلمي')
                    ->formatStateUsing(function ($record) {
                        return $record->student->educations->take(3)->pluck('name')->implode(', ');
                    })->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('student.educations', function (Builder $studentQuery) use ($search) {
                            $studentQuery->where('name', 'like', "%{$search}%");

                        });
                    })->toggleable(),

                TextColumn::make('entry_time')
                    ->label('تاريخ الدخول')->sortable()
                    ->dateTime('Y-m-d H:i'),

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

                CheckboxColumn::make('use_printer')->label('طباعة'),
                CheckboxColumn::make('use_mecrator')->label('ميكراتور'),
                CheckboxColumn::make('use_drawer')->label('مرسم'),
            ])
            ->filters([
                Filter::make('entry_time')
                    ->label('التاريخ')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('من')->default(today()),
                        \Filament\Forms\Components\DatePicker::make('to')->label('إلى')->default(now()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn($q, $date) => $q->whereDate('entry_time', '>=', $date))
                            ->when($data['to'], fn($q, $date) => $q->whereDate('entry_time', '<=', $date));
                    }),
                SelectFilter::make('entry_type')
                    ->label('نوع الدخولية')
                    ->options(__('general.entry-types'))
                    ->searchable()
                    ->multiple()
                    ->preload(),
                SelectFilter::make('student.educations')
                    ->label('المؤهل العلمي')
                    ->relationship('student.educations', 'name')
                    ->searchable()
                    ->multiple()
                    ->preload(),
                SelectFilter::make('center.city.name')
                    ->label('المدينة')
                    ->relationship('center.city', 'name')
                    ->searchable()
                    ->visible(auth()->user()->role === Roles::GeneralAdmin())
                    ->multiple()
                    ->preload(),
                SelectFilter::make('center_id')
                    ->label('المركز')
                    ->relationship('center', 'name')
                    ->searchable()
                    ->hidden(auth()->user()->role === Roles::CenterAdmin())
                    ->multiple()
                    ->preload(),
            ])
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('فلتر'),
            )
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])->paginated([20, 50, 100, 200]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEntries::route('/'),

        ];
    }

    public static function getWidgets(): array
    {
        return [
            EntryStats::class ,
        ];
    }
}
