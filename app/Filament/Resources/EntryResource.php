<?php

namespace App\Filament\Resources;

use App\Enums\Gender;
use App\Enums\Religion;
use App\Enums\Roles;
use App\Filament\Exports\EntryExporter;
use App\Filament\Resources\EntryResource\Pages;
use App\Filament\Resources\EntryResource\RelationManagers\ServicesRelationManager;
use App\Filament\Resources\EntryResource\Widgets\EntryStats;
use App\Filament\Resources\EntryResource\Widgets\EntryTypeChart;
use App\Models\Education;
use App\Models\Service;
use App\Models\EntryService;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\StudentEducation;
use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        // Eager-load relations used in table columns to eliminate N+1 queries
        $query = parent::getEloquentQuery()->with([
            'student.studentEducations',
            'center.city',
        ]);

        return match (true) {
            $user->role === Roles::GeneralAdmin(),
            $user->role === Roles::Observer()    => $query,

            $user->role === Roles::CityAdmin(),
            $user->role === Roles::MealAdmin()   => $query->whereHas(
                'center',
                fn ($q) => $q->where('city_id', $user->city_id)
            ),

            $user->role === Roles::CenterAdmin() => $query->where('center_id', $user->center_id),

            default => $query->whereRaw('1 = 0'),
        };
    }

    public static function table(Table $table): Table
    {
        // Resolve once — reused across all column/filter/action closures
        $user        = auth()->user();
        $isObserver  = $user->role === Roles::Observer();
        $isMealAdmin = $user->role === Roles::MealAdmin();

        return $table
            ->columns([
                TextColumn::make('student.firstname')
                    ->label('اسم الطالب')
                    ->formatStateUsing(fn (Model $record) => implode(' ', [
                        $record->student->firstname,
                        $record->student->father_name,
                        $record->student->lastname,
                    ]))
                    ->sortable()
                    ->searchable(
                        query: fn (Builder $query, string $search): Builder => $query->whereHas(
                            'student',
                            fn (Builder $q) => $q
                                ->where('firstname', 'like', "%{$search}%")
                                ->orWhere('lastname', 'like', "%{$search}%")
                                ->orWhere('father_name', 'like', "%{$search}%")
                        ),
                    ),

                IconColumn::make('student.completed_data')
                    ->label('حالة البيانات')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable()
                    ->tooltip(fn ($record) => $record->student->completed_data
                        ? 'البيانات مكتملة'
                        : 'البيانات غير مكتملة'),

                TextColumn::make('student.national_number')
                    ->label('الرقم الوطني / الإقامة')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('student.phone')
                    ->label('الموبايل')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('student.gender')
                    ->label('الجنس')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("general.genders.{$state}"))
                    ->color(fn (int $state): string => match ($state) {
                        Gender::Unknown() => 'warning',
                        Gender::Male()    => 'info',
                        Gender::Female()  => 'danger',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('student.religion')
                    ->label('الديانة')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("general.religions.{$state}"))
                    ->color(fn (int $state): string => match ($state) {
                        Religion::Unknown()   => 'warning',
                        Religion::Christian() => 'success',
                        Religion::Muslim()    => 'secondary',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('student.whatsapp')
                    ->label('واتساب')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('entry_type')
                    ->label('نوع الدخول')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => __('general.entry-types.' . $state)),

                TextColumn::make('student.studentEducations.level')
                    ->label('مستوى التعليم')
                    ->formatStateUsing(function (Model $record) {
                        // Uses the already eager-loaded relation — no extra query
                        $lastEdu = $record->student
                            ->studentEducations
                            ->sortByDesc('start_date')
                            ->first();

                        return $lastEdu
                            ? __('general.edu-levels.' . (string) $lastEdu->level)
                            : '-';
                    })
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(
                        query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                            StudentEducation::select('level')
                                ->whereColumn('student_id', 'students.id')
                                ->orderBy('start_date', 'desc')
                                ->limit(1),
                            $direction
                        ),
                    )
                    ->searchable(
                        query: fn (Builder $query, string $search): Builder => $query->whereHas(
                            'student.studentEducations',
                            fn (Builder $q) => $q->where('level', 'like', "%{$search}%")
                        ),
                    ),

                TextColumn::make('student.education')
                    ->label('المؤهل العلمي')
                    ->sortable(
                        query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                            StudentEducation::select('education_id')
                                ->whereColumn('student_id', 'students.id')
                                ->orderBy('start_date', 'desc')
                                ->limit(1),
                            $direction
                        ),
                    )
                    ->toggleable(),

                TextColumn::make('services_list')
                    ->label('الخدمات المقدمة')
                    ->formatStateUsing(fn ($record) => $record->services_list ?: '-')
                    ->toggleable(),

                TextColumn::make('total_amount')
                    ->label('الإجمالي')
                    ->formatStateUsing(fn ($record) => $record->total_amount . ' SYP')
                    ->color('success')
                    ->toggleable(),

                TextColumn::make('entry_time')
                    ->label('تاريخ الدخول')
                    ->sortable()
                    ->dateTime('Y-m-d H:i'),

                TextColumn::make('center.city.name')
                    ->label('المدينة')
                    ->sortable()
                    ->visible($user->role === Roles::GeneralAdmin())
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('center.name')
                    ->label('المركز')
                    ->sortable()
                    ->hidden($user->role === Roles::CenterAdmin())
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersFormColumns(4)
            ->filters([
                Filter::make('entry_time')
                    ->label('التاريخ')
                    ->form([
                        DateTimePicker::make('from')
                            ->label('من')
                            ->default(today()->startOfDay())
                            ->columnSpan(1),
                        DateTimePicker::make('to')
                            ->label('إلى')
                            ->default(today()->endOfDay())
                            ->columnSpan(1),
                    ])
                    ->columnSpan(2)
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn ($q, $date) => $q->where('entry_time', '>=', $date))
                        ->when($data['to'],   fn ($q, $date) => $q->where('entry_time', '<=', $date))
                    ),

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
                        true:  fn (Builder $query) => $query->whereHas('student', fn ($q) => $q->where('completed_data', true)),
                        false: fn (Builder $query) => $query->whereHas('student', fn ($q) => $q->where('completed_data', false)),
                        blank: fn (Builder $query) => $query,
                    )
                    ->columnSpan(1),

                SelectFilter::make('center.city.name')
                    ->label('المدينة')
                    ->relationship('center.city', 'name')
                    ->searchable()
                    ->columnSpan(1)
                    ->visible($user->role === Roles::GeneralAdmin())
                    ->multiple()
                    ->preload(),

                SelectFilter::make('center_id')
                    ->label('المركز')
                    ->relationship('center', 'name')
                    ->searchable()
                    ->columnSpan($user->role === Roles::GeneralAdmin() ? 1 : 4)
                    ->hidden($user->role === Roles::CenterAdmin())
                    ->multiple()
                    ->preload(),

                SelectFilter::make('student.education')
                    ->label('المؤهل العلمي')
                    ->options(Education::pluck('name', 'id'))
                    ->searchable()
                    ->multiple()
                    ->preload()
                    ->columnSpan(2)
                    ->query(function (Builder $query, array $data, $livewire): Builder {
                        if (empty($data['values'])) {
                            return $query;
                        }

                        $entryTimeData = $livewire->tableFilters['entry_time'] ?? null;
                        $from = $entryTimeData['from'] ?? null;
                        $to   = $entryTimeData['to']   ?? null;

                        return $query->whereHas(
                            'student.studentEducations',
                            function (Builder $q) use ($data, $from, $to) {
                                $q->whereIn('education_id', (array) $data['values']);

                                if ($from && $to) {
                                    $q->where(function ($inner) use ($from, $to) {
                                        $inner->whereColumn('start_date', '<=', 'entry_time')
                                            ->where(function ($sub) {
                                                $sub->whereColumn('end_date', '>=', 'entry_time')
                                                    ->orWhereNull('end_date');
                                            })
                                            ->whereBetween('entry_time', [
                                                Carbon::parse($from),
                                                Carbon::parse($to),
                                            ]);
                                    });
                                }
                            }
                        );
                    }),

                SelectFilter::make('student.studentEducations.level')
                    ->label(__('general.labels.education_level'))
                    ->options(__('general.edu-levels'))
                    ->searchable()
                    ->columnSpan(1)
                    ->query(function (Builder $query, array $data): Builder {
                        if (isset($data['value']) && $data['value'] !== null && $data['value'] !== '') {
                            return $query->whereHas(
                                'student.studentEducations',
                                fn (Builder $q) => $q->where('level', (int) $data['value'])
                            );
                        }

                        return $query;
                    })
                    ->preload(),

                TernaryFilter::make('student.national_number')
                    ->label('الرقم الوطني / الإقامة')
                    ->placeholder('الكل')
                    ->trueLabel('يوجد')
                    ->falseLabel('لا يوجد')
                    ->queries(
                        true:  fn (Builder $query) => $query->whereHas('student', fn ($q) => $q->whereNotNull('national_number')),
                        false: fn (Builder $query) => $query->whereHas('student', fn ($q) => $q->whereNull('national_number')),
                        blank: fn (Builder $query) => $query,
                    )
                    ->columnSpan(1),

                SelectFilter::make('student.gender')
                    ->label('الجنس')
                    ->options(__('general.genders'))
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        if (isset($data['value']) && $data['value'] !== null && $data['value'] !== '') {
                            return $query->whereHas(
                                'student',
                                fn (Builder $q) => $q->where('gender', (int) $data['value'])
                            );
                        }

                        return $query;
                    }),

                SelectFilter::make('student.religion')
                    ->label('الديانة')
                    ->options(__('general.religions'))
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        if (isset($data['value']) && $data['value'] !== null && $data['value'] !== '') {
                            return $query->whereHas(
                                'student',
                                fn (Builder $q) => $q->where('religion', (int) $data['value'])
                            );
                        }

                        return $query;
                    }),
            ])
            ->filtersTriggerAction(fn (Action $action) => $action->button()->label(''))
            ->actions([
                ViewAction::make('view_student')
                    ->label('')
                    ->color('info')
                    ->icon('heroicon-o-user')
                    ->url(fn (Model $record) => StudentResource::getUrl('view', ['record' => $record->student_id])),
Action::make('addServices')
    ->label('')
    ->icon('heroicon-o-shopping-cart')
    ->url(fn ($record) =>
        EntryResource::getUrl('add-services', ['record' => $record])
    ),

                Tables\Actions\EditAction::make()
                    ->label('')
                    ->hidden(fn () => $isObserver)
                    ->disabled(fn ($record) => ! $record->student->completed_data)
                    ->tooltip(fn ($record) => ! $record->student->completed_data
                        ? '❌ لا يمكن التعديل: بيانات الطالب غير مكتملة'
                        : null),

                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->hidden(fn () => $isObserver),
            ])
            ->bulkActions([
                ExportBulkAction::make()
                    ->exporter(EntryExporter::class)
                    ->formats([ExportFormat::Xlsx]),
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->hidden(fn () => $isObserver),
                ])->hidden(fn () => $isObserver),
            ])
            ->paginated([20, 50, 100, 200]);
    }

    public static function getRelations(): array
    {
        return [
            ServicesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEntries::route('/'),
            'edit'  => Pages\EditEntry::route('/{record}/edit'),
            'add-services' => Pages\AddEntryServices::route('/{record}/services'),
            'view'  => Pages\ViewEntry::route('/{record}'),

        ];
    }

    public static function getWidgets(): array
    {
        return [
            EntryStats::class,
            EntryTypeChart::class,
        ];
    }

    public static function canEdit(Model $record): bool
    {
        $role = auth()->user()->role;

        return $role !== Roles::MealAdmin() && $role !== Roles::Observer();
    }

    public static function canDelete(Model $record): bool
    {
        $role = auth()->user()->role;

        return $role !== Roles::MealAdmin() && $role !== Roles::Observer();
    }
}
