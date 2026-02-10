<?php

namespace App\Filament\Resources;

use App\Enums\Gender;
use App\Enums\Religion;
use App\Enums\Roles;
use App\Filament\Exports\EntryExporter;
use App\Filament\Exports\StudentExporter;
use App\Filament\Resources\EntryResource\Pages;
use App\Filament\Resources\EntryResource\Widgets\EntryStats;
use App\Models\Center;
use App\Models\StudentEntry;
use Filament\Forms\Components\{Checkbox, DateTimePicker, Fieldset, Placeholder, Select};
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\{CheckboxColumn, IconColumn, TextColumn};
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Actions\ViewAction;
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

    public static function form(Form $form): Form
    {
        return $form->schema([
            Placeholder::make('student_full_name')
                ->label('الاسم')
                ->content(fn($record) => $record?->student ? $record->student->firstname . ' ' . $record->student->father_name . ' ' . $record->student->lastname : '-'),
            Select::make('center_id')
                ->label(__('general.labels.center'))
                ->options(function () {
                    return Center::where('is_active', 1)
                        ->when(auth()->user()->city_id, fn($query, $cityId) => $query->where('city_id', $cityId))
                        ->get()
                        ->pluck('name', 'id');
                })
                ->default(fn() => auth()->user()->center_id)
                ->visible(auth()->user()->role !== Roles::CenterAdmin())
                ->required()
                ->searchable(),
            Select::make('entry_type')->label(__('general.labels.entry_type'))->default(1)->options(__('general.entry-types'))->columnSpan(1)->required(),
            DateTimePicker::make('entry_time')->label(__('general.labels.entry_time'))->default(now())->columnSpan(1)->required(),
            Fieldset::make('Services')
                ->label('خدمات')
                ->schema([
                    Checkbox::make('use_printer')->reactive()->label('خدمة طباعة')->default(false),
                    Checkbox::make('use_drawer')->reactive()->label('خدمة مرسم')->default(false),
                    Checkbox::make('use_mecrator')->reactive()->label('خدمة ميكراتور')->default(false),
                    Checkbox::make('use_plotter')->reactive()->label('خدمة بلوتر')->default(false),
                ])
                ->columns(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        $isObserver = auth()->user()->role === Roles::Observer();
        $isMealAdmin = auth()->user()->role === Roles::MealAdmin();

        return $table
            ->columns([
                TextColumn::make('student.firstname')->label('اسم الطالب')->formatStateUsing(fn(Model $record) => $record->student->firstname . ' ' . $record->student->father_name . ' ' . $record->student->lastname)->sortable()->searchable(
                    query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('student', function (Builder $studentQuery) use ($search) {
                            $studentQuery
                                ->where('firstname', 'like', "%{$search}%")
                                ->orWhere('lastname', 'like', "%{$search}%")
                                ->orWhere('father_name', 'like', "%{$search}%");
                        });
                    },
                ),
                IconColumn::make('student.completed_data')
                    ->label('حالة البيانات')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable()
                    ->tooltip(fn($record) => $record->student->completed_data ? 'البيانات مكتملة' : 'البيانات غير مكتملة'),
                TextColumn::make('student.national_number')
                    ->label('الرقم الوطني / الإقامة')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('student.phone')->label('الموبايل')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('student.gender')
                    ->label('الجنس')
                    ->sortable()
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
                TextColumn::make('student.religion')
                    ->label('الديانة')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn(string $state) => __("general.religions.{$state}"))
                    ->color(
                        fn(int $state): string => match ($state) {
                            Religion::Unknown() => 'warning',
                            Religion::Christian() => 'success',
                            Religion::Muslim() => 'secondary',
                        },
                    )
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('student.whatsapp')->label('واتساب')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('entry_type')->label('نوع الدخول')->sortable()->formatStateUsing(fn($state) => __('general.entry-types.' . $state)),
                TextColumn::make('student.educations')
                    ->label('المؤهل العلمي')
                    ->formatStateUsing(function ($record) {
                        return $record->student->educations->take(3)->pluck('name')->implode(', ');
                    })
                    ->searchable(
                        query: function (Builder $query, string $search): Builder {
                            return $query->whereHas('student.educations', function (Builder $studentQuery) use ($search) {
                                $studentQuery->where('name', 'like', "%{$search}%");
                            });
                        },
                    )
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
                CheckboxColumn::make('use_printer')
                    ->disabled($isObserver || $isMealAdmin)
                    ->label('طباعة')
                    ->sortable(),
                CheckboxColumn::make('use_mecrator')
                    ->disabled($isObserver || $isMealAdmin)
                    ->label('ميكراتور')
                    ->sortable(),
                CheckboxColumn::make('use_drawer')
                    ->disabled($isObserver || $isMealAdmin)
                    ->label('مرسم')
                    ->sortable(),
                CheckboxColumn::make('use_plotter')
                    ->disabled($isObserver || $isMealAdmin)
                    ->label('بلوتر')
                    ->sortable(),
            ])
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersFormColumns(4)
            ->filters([
                Filter::make('entry_time')
                    ->label('التاريخ')
                    ->form([
                        \Filament\Forms\Components\DateTimePicker::make('from')
                            ->label('من')
                            ->default(today()->startOfDay())
                            ->columnSpan(1),
                        \Filament\Forms\Components\DateTimePicker::make('to')
                            ->label('إلى')
                            ->default(today()->endOfDay())
                            ->columnSpan(1),
                    ])
                    ->columnSpan(2)
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['from'], fn($q, $date) => $q->where('entry_time', '>=', $date))->when($data['to'], fn($q, $date) => $q->where('entry_time', '<=', $date));
                    }),
                SelectFilter::make('entry_type')
                    ->label('نوع الدخولية')
                    ->options(__('general.entry-types'))
                    ->searchable()
                    ->multiple()
                    ->preload()
                    ->columnSpan(1),
                TernaryFilter::make('student.completed_data')
                    ->label('حالة بيانات الطالب')
                    ->placeholder('الكل')
                    ->trueLabel('نعم - البيانات مكتملة')
                    ->falseLabel('لا - البيانات غير مكتملة')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('student', function ($query){
                            $query->where('completed_data', true);
                        }),
                        false: fn (Builder $query) => $query->whereHas('student', function ($query){
                            $query->where('completed_data', false);
                        }),
                        blank: fn (Builder $query) => $query,
                    )
                    ->columnSpan(1),

                SelectFilter::make('center.city.name')
                    ->label('المدينة')
                    ->relationship('center.city', 'name')
                    ->searchable()
                    ->columnSpan(2)
                    ->visible(auth()->user()->role === Roles::GeneralAdmin())
                    ->multiple()
                    ->preload(),
                SelectFilter::make('center_id')
                    ->label('المركز')
                    ->relationship('center', 'name')
                    ->searchable()
                    ->columnSpan(auth()->user()->role === Roles::GeneralAdmin() ? 2: 4)
                    ->hidden(auth()->user()->role === Roles::CenterAdmin())
                    ->multiple()
                    ->preload(),
                SelectFilter::make('student.educations')->label('المؤهل العلمي')->relationship('student.educations', 'name')->searchable()->multiple()->preload(),
                TernaryFilter::make('student.national_number')
                    ->label('الرقم الوطني / الإقامة')
                    ->placeholder('الكل')
                    ->trueLabel('يوجد')
                    ->falseLabel('لا يوجد')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('student' , function ($query){
                            $query->whereNotNull('national_number');
                        }),
                        false: fn (Builder $query) => $query->whereHas('student' , function ($query){
                            $query->whereNull('national_number');
                        }),
                        blank: fn (Builder $query) => $query,
                    ),
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


                TernaryFilter::make('use_printer')
                    ->label(__('general.labels.print-service'))
                    ->placeholder('الكل')
                    ->trueLabel('مفعّل')
                    ->falseLabel('غير مفعّل')
                    ->queries(
                        true: fn(Builder $query) => $query->where('use_printer', true),
                        false: fn(Builder $query) => $query->where('use_printer', false),
                        blank: fn(Builder $query) => $query,
                    )
                    ->columnSpan(1),
                TernaryFilter::make('use_mecrator')
                    ->label(__('general.labels.mecrator-service'))
                    ->placeholder('الكل')
                    ->trueLabel('مفعّل')
                    ->falseLabel('غير مفعّل')
                    ->queries(
                        true: fn (Builder $query) => $query->where('use_mecrator', true),
                        false: fn (Builder $query) => $query->where('use_mecrator', false),
                        blank: fn (Builder $query) => $query,
                    )
                    ->columnSpan(1),
                TernaryFilter::make('use_drawer')
                    ->label(__('general.labels.drawer-service'))
                    ->placeholder('الكل')
                    ->trueLabel('مفعّل')
                    ->falseLabel('غير مفعّل')
                    ->queries(
                        true: fn (Builder $query) => $query->where('use_drawer', true),
                        false: fn (Builder $query) => $query->where('use_drawer', false),
                        blank: fn (Builder $query) => $query,
                    )
                    ->columnSpan(1),
                TernaryFilter::make('use_plotter')
                    ->label(__('general.labels.plotter-service'))
                    ->placeholder('الكل')
                    ->trueLabel('مفعّل')
                    ->falseLabel('غير مفعّل')
                    ->queries(
                        true: fn (Builder $query) => $query->where('use_plotter', true),
                        false: fn (Builder $query) => $query->where('use_plotter', false),
                        blank: fn (Builder $query) => $query,
                    )
                    ->columnSpan(1),
            ])
            ->filtersTriggerAction(fn(Action $action) => $action->button()->label(''))
            ->actions([
                ViewAction::make('view_student')
                    ->label('عرض الطالب')
                    ->color('info')
                    ->icon('heroicon-o-user')
                    ->url(fn(Model $record) => \App\Filament\Resources\StudentResource::getUrl('view', ['record' => $record->student_id])),
                Tables\Actions\EditAction::make()
                    ->hidden(fn() => auth()->user()->role === Roles::Observer())
                    ->disabled(function ($record) {
                        return !$record->student->completed_data;
                    })
                    ->tooltip(function ($record) {
                        if (!$record->student->completed_data) {
                            return '❌ لا يمكن التعديل: بيانات الطالب غير مكتملة';
                        }
                        return null;
                    }),
                Tables\Actions\DeleteAction::make()->hidden(fn() => auth()->user()->role === Roles::Observer())
            ])
            ->bulkActions([
                ExportBulkAction::make()
                    ->exporter(EntryExporter::class)
                    ->formats([ExportFormat::Xlsx])
                    ->hidden(fn() => auth()->user()->role === Roles::Observer()),
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()->hidden(fn() => auth()->user()->role === Roles::Observer())])->hidden(fn() => auth()->user()->role === Roles::Observer()),
            ])
            ->paginated([20, 50, 100, 200]);
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
