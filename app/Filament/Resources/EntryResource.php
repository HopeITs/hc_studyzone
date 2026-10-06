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
use App\Models\LockedMonth;
use App\Models\StudentEducation;
use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Components\DateTimePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\CheckboxColumn;
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

class EntryResource extends Resource
{
    protected static ?string $model = StudentEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 7;

    public static function getNavigationLabel(): string
    {
        return __('general.labels.entries');
    }

    public static function getModelLabel(): string
    {
        return __('general.labels.entry_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.labels.entries');
    }

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
            $user->role === Roles::Observer() => $query,

            $user->role === Roles::CityAdmin(),
            $user->role === Roles::MealAdmin() => $query->whereHas(
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
        $user = auth()->user();
        $isObserver = $user->role === Roles::Observer();
        $isMealAdmin = $user->role === Roles::MealAdmin();

        return $table
            ->defaultSort('entry_time', 'desc')
            ->columns([
                TextColumn::make('student.firstname')
                    ->label(__('general.labels.student_name'))
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
                    ->label(__('general.labels.data_status'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable()
                    ->tooltip(fn ($record) => $record->student->completed_data
                        ? __('general.messages.completed_data_tooltip')
                        : __('general.messages.incomplete_data_tooltip')),

                TextColumn::make('student.national_number')
                    ->label(__('general.labels.national_number_or_residency'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('student.phone')
                    ->label(__('general.labels.mobile'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('student.gender')
                    ->label(__('general.labels.gender'))
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("general.genders.{$state}"))
                    ->color(fn (int $state): string => match ($state) {
                        Gender::Unknown() => 'warning',
                        Gender::Male() => 'info',
                        Gender::Female() => 'danger',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('student.religion')
                    ->label(__('general.labels.religion'))
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("general.religions.{$state}"))
                    ->color(fn (int $state): string => match ($state) {
                        Religion::Unknown() => 'warning',
                        Religion::Christian() => 'success',
                        Religion::Muslim() => 'secondary',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('student.whatsapp')
                    ->label(__('general.labels.whatsapp'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('entry_type')
                    ->label(__('general.labels.entry_type'))
                    ->sortable()
                    ->formatStateUsing(fn ($state) => __('general.entry-types.'.$state)),

                TextColumn::make('purpose')
                    ->label(__('general.labels.purpose'))
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn ($state) => __('general.purposes.'.$state))
                    ->color(fn (int $state): string => match ($state) {
                        \App\Enums\EntryPurpose::Unknown() => 'warning',
                        \App\Enums\EntryPurpose::Studying() => 'info',
                        \App\Enums\EntryPurpose::Working() => 'success',
                    })
                    ->toggleable(),

                CheckboxColumn::make('use_internet')
                    ->label(__('general.labels.internet-service'))
                    ->disabled(fn ($record) => ! \App\Helpers\ServiceHelper::centerHasInternetService($record->center_id) || LockedMonth::isLocked($record->entry_time))
                    ->extraAttributes(fn ($record) => \App\Helpers\ServiceHelper::centerHasInternetService($record->center_id) ? [] : ['class' => 'hidden'])
                    ->updateStateUsing(fn ($record, $state) => \App\Helpers\ServiceHelper::toggleInternetService($record, (bool) $state)),

                TextColumn::make('student.studentEducations.level')
                    ->label(__('general.labels.education_level'))
                    ->formatStateUsing(function (Model $record) {
                        // Uses the already eager-loaded relation — no extra query
                        $lastEdu = $record->student
                            ->studentEducations
                            ->sortByDesc('start_date')
                            ->first();

                        return $lastEdu
                            ? __('general.edu-levels.'.(string) $lastEdu->level)
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
                    ->label(__('general.labels.education'))
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
                    ->label(__('general.labels.provided_services'))
                    ->formatStateUsing(fn ($record) => $record->services_list ?? '-')
                    ->toggleable(),

                TextColumn::make('total_amount')
                    ->label(__('general.labels.total'))
                    ->formatStateUsing(fn ($record) => $record->total_amount.' SYP')
                    ->color('success')
                    ->extraAttributes(['class' => 'donor-hide'])
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('general.labels.old_total'))
                    ->formatStateUsing(fn ($record) => $record->total_amount * 100 .' SYP')
                    ->color('info')
                    ->extraAttributes(['class' => 'donor-hide'])
                    ->toggleable(),

                TextColumn::make('entry_time')
                    ->label(__('general.labels.entry_date'))
                    ->sortable()
                    ->dateTime('Y-m-d H:i'),

                TextColumn::make('center.city.name')
                    ->label(__('general.labels.city'))
                    ->sortable()
                    ->visible($user->role === Roles::GeneralAdmin())
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('center.name')
                    ->label(__('general.labels.center'))
                    ->sortable()
                    ->hidden($user->role === Roles::CenterAdmin())
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersFormColumns(4)
            ->deferFilters()
            ->filters([
                Filter::make('entry_time')
                    ->label(__('general.labels.date'))
                    ->form([
                        DateTimePicker::make('from')
                            ->label(__('general.labels.from'))
                            ->default(today()->startOfDay())
                            ->columnSpan(1),
                        DateTimePicker::make('to')
                            ->label(__('general.labels.to'))
                            ->default(today()->endOfDay())
                            ->columnSpan(1),
                    ])
                    ->columnSpan(2)
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn ($q, $date) => $q->where('entry_time', '>=', $date))
                        ->when($data['to'], fn ($q, $date) => $q->where('entry_time', '<=', $date))
                    ),

                SelectFilter::make('entry_type')
                    ->label(__('general.labels.entry_type'))
                    ->options(__('general.entry-types'))
                    ->searchable()
                    ->multiple()
                    ->preload()
                    ->columnSpan(1),

                SelectFilter::make('purpose')
                    ->label(__('general.labels.purpose'))
                    ->options(__('general.purposes'))
                    ->multiple()
                    ->preload()
                    ->columnSpan(1),

                TernaryFilter::make('student.completed_data')
                    ->label(__('general.labels.student_data_status'))
                    ->placeholder(__('general.labels.all'))
                    ->trueLabel(__('general.labels.yes').' - '.__('general.labels.completed_data'))
                    ->falseLabel(__('general.labels.no').' - '.__('general.labels.incomplete_data'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('student', fn ($q) => $q->where('completed_data', true)),
                        false: fn (Builder $query) => $query->whereHas('student', fn ($q) => $q->where('completed_data', false)),
                        blank: fn (Builder $query) => $query,
                    )
                    ->columnSpan(1),

                SelectFilter::make('center.city.name')
                    ->label(__('general.labels.city'))
                    ->relationship('center.city', 'name')
                    ->searchable()
                    ->columnSpan(1)
                    ->visible($user->role === Roles::GeneralAdmin())
                    ->multiple()
                    ->preload(),

                SelectFilter::make('center_id')
                    ->label(__('general.labels.center'))
                    ->relationship('center', 'name')
                    ->searchable()
                    ->columnSpan($user->role === Roles::GeneralAdmin() ? 1 : 4)
                    ->hidden($user->role === Roles::CenterAdmin())
                    ->multiple()
                    ->preload(),

                    TernaryFilter::make('student.national_number')
                    ->label(__('general.labels.national_number_or_residency'))
                    ->placeholder(__('general.labels.all'))
                    ->trueLabel(__('general.labels.exists'))
                    ->falseLabel(__('general.labels.not_exists'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('student', fn ($q) => $q->whereNotNull('national_number')),
                        false: fn (Builder $query) => $query->whereHas('student', fn ($q) => $q->whereNull('national_number')),
                        blank: fn (Builder $query) => $query,
                    )
                    ->columnSpan(1),

                SelectFilter::make('student.education')
                    ->label(__('general.labels.education'))
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
                        $to = $entryTimeData['to'] ?? null;

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
                    ->multiple()
                    ->searchable()
                    ->columnSpan(2)
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['values'])) {
                            return $query;
                        }

                        return $query->whereHas(
                            'student.studentEducations',
                            fn (Builder $q) => $q->whereIn('level', $data['values'])
                        );
                    })
                    ->preload(),



                SelectFilter::make('student.gender')
                    ->label(__('general.labels.gender'))
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
                    ->label(__('general.labels.religion'))
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

                TernaryFilter::make('use_printer')->label(__('general.labels.print-service'))->placeholder(__('general.labels.all'))->trueLabel(__('general.labels.enabled'))->falseLabel(__('general.labels.disabled'))->queries(true: fn (Builder $query) => $query->where('use_printer', true), false: fn (Builder $query) => $query->where('use_printer', false), blank: fn (Builder $query) => $query)->columnSpan(1),
                TernaryFilter::make('use_mecrator')->label(__('general.labels.mecrator-service'))->placeholder(__('general.labels.all'))->trueLabel(__('general.labels.enabled'))->falseLabel(__('general.labels.disabled'))->queries(true: fn (Builder $query) => $query->where('use_mecrator', true), false: fn (Builder $query) => $query->where('use_mecrator', false), blank: fn (Builder $query) => $query)->columnSpan(1),
                TernaryFilter::make('use_drawer')->label(__('general.labels.drawer-service'))->placeholder(__('general.labels.all'))->trueLabel(__('general.labels.enabled'))->falseLabel(__('general.labels.disabled'))->queries(true: fn (Builder $query) => $query->where('use_drawer', true), false: fn (Builder $query) => $query->where('use_drawer', false), blank: fn (Builder $query) => $query)->columnSpan(1),
                TernaryFilter::make('use_plotter')->label(__('general.labels.plotter-service'))->placeholder(__('general.labels.all'))->trueLabel(__('general.labels.enabled'))->falseLabel(__('general.labels.disabled'))->queries(true: fn (Builder $query) => $query->where('use_plotter', true), false: fn (Builder $query) => $query->where('use_plotter', false), blank: fn (Builder $query) => $query)->columnSpan(1),
                TernaryFilter::make('use_internet')->label(__('general.labels.internet-service'))->placeholder(__('general.labels.all'))->trueLabel(__('general.labels.enabled'))->falseLabel(__('general.labels.disabled'))->queries(true: fn (Builder $query) => $query->where('use_internet', true), false: fn (Builder $query) => $query->where('use_internet', false), blank: fn (Builder $query) => $query)->columnSpan(1),
                TernaryFilter::make('reservation')->label(__('general.labels.reservation'))->placeholder(__('general.labels.all'))->trueLabel(__('general.labels.enabled'))->falseLabel(__('general.labels.disabled'))->queries(true: fn (Builder $query) => $query->where('reservation', true), false: fn (Builder $query) => $query->where('reservation', false), blank: fn (Builder $query) => $query)->columnSpan(1),
                TernaryFilter::make('stationary')->label(__('general.labels.stationary'))->placeholder(__('general.labels.all'))->trueLabel(__('general.labels.enabled'))->falseLabel(__('general.labels.disabled'))->queries(true: fn (Builder $query) => $query->where('stationary', true), false: fn (Builder $query) => $query->where('stationary', false), blank: fn (Builder $query) => $query)->columnSpan(1),

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
                    ->url(fn ($record) => EntryResource::getUrl('add-services', ['record' => $record]))
                    ->disabled(fn ($record) => LockedMonth::isLocked($record->entry_time)),

                Tables\Actions\EditAction::make()
                    ->label('')
                    ->hidden(fn () => $isObserver)
                    ->disabled(fn ($record) => ! $record->student->completed_data)
                    ->tooltip(fn ($record) => ! $record->student->completed_data
                        ? __('general.labels.create_entry_disabled_tooltip')
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
            'edit' => Pages\EditEntry::route('/{record}/edit'),
            'add-services' => Pages\AddEntryServices::route('/{record}/services'),
            'view' => Pages\ViewEntry::route('/{record}'),

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

        if ($role === Roles::MealAdmin() || $role === Roles::Observer()) {
            return false;
        }

        return ! LockedMonth::isLocked($record->entry_time);
    }

    public static function canDelete(Model $record): bool
    {
        $role = auth()->user()->role;

        if ($role === Roles::MealAdmin() || $role === Roles::Observer()) {
            return false;
        }

        return ! LockedMonth::isLocked($record->entry_time);
    }
}
