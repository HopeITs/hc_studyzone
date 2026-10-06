<?php

namespace App\Filament\Resources;

use Filament\Support\Enums\Width;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use App\Enums\Roles;
use App\Filament\Resources\CustomActivitylogResource\Pages\ListActivitylogs;
use App\Filament\Resources\CustomActivitylogResource\Pages\ViewActivitylog;
use App\Models\Center;
use App\Models\City;
use App\Models\Education;
use App\Models\Street;
use App\Models\Student;
use App\Models\StudentEducation;
use App\Models\StudentEntry;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Rmsramos\Activitylog\Resources\Activitylog\ActivitylogResource;

class CustomActivitylogResource extends ActivitylogResource
{
    /**
     * Grouped under "إدارة النظام" alongside EntryAuditResource/StudentAuditResource
     * instead of the vendor plugin's own placement.
     */
    public static function getNavigationGroup(): ?string
    {
        return 'إدارة النظام';
    }

    /**
     * The base ActivitylogResource::canAccess() falls back to the plugin's blanket
     * ->isAuthorized() flag when no Policy exists for the Activity model (this app has
     * none), which doesn't distinguish between roles. Restrict to General Admin only.
     */
    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, [Roles::GeneralAdmin(), Roles::Observer()], true);
    }

    public static function table(Table $table): Table
    {
        // 1. Get the pre-built table from the Rmsramos plugin
        $table = parent::table($table);

        // 1b. Add a filter on the *data's own date* (entry_time / education start-end range),
        // not on the log's created_at, so we can audit "what changed for August's data" regardless
        // of when the change was made. Plus a subject-type filter, and a backdating detector.
        $table->pushFilters([
            static::getDataPeriodFilterComponent(),
            static::getSubjectTypeFilterComponent(),
            static::getRegistrationMismatchFilterComponent(),
            static::getCenterFilterComponent(),
            static::getEntryTimeEditFilterComponent(),
            static::getDeletionGapFilterComponent(),
        ]);

        // Keep related fields together so the wide filter panel remains easy to scan.
        $table
            ->filtersFormColumns(1)
            ->filtersFormWidth(Width::SevenExtraLarge)
            ->deferFilters()
            ->filtersFormSchema(fn (array $filters): array => [
                Section::make('الفلاتر الأساسية')
                    ->compact()
                    ->columns(4)
                    ->schema([
                        $filters['created_at']->columnSpan(2)->columns(2),
                        $filters['event'],
                        $filters['subject_type'],
                    ]),

                Section::make('فترة البيانات والمركز')
                    ->compact()
                    ->columns(3)
                    ->schema([
                        $filters['data_period']->columnSpan(2)->columns(2),
                        $filters['center_id'],
                    ]),

                Section::make('فلاتر التدقيق المتقدمة')
                    ->compact()
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        Fieldset::make('حدث الإنشاء')
                            ->schema([
                                $filters['registration_mismatch']->columnSpanFull()->columns(2),
                            ]),

                        Fieldset::make('حدث الحذف')
                            ->schema([
                                $filters['deletion_gap']->columnSpanFull()->columns(2),
                            ]),

                        Fieldset::make('حدث التعديل')
                            ->columnSpanFull()
                            ->schema([
                                $filters['entry_time_edited']->columnSpanFull()->columns(3),
                            ]),
                    ]),
            ]);

        // 1c. Show the registration-gap (entry_time vs. when the log row was actually
        // written) as a sortable column, so it's visible at a glance without opening
        // the registration_mismatch filter.
        $table->pushColumns([
            static::getRegistrationGapColumnComponent(),
            static::getEntryTimeEditColumnComponent(),
            static::getDeletionGapColumnComponent(),
        ]);

        // 2. Grab all the columns
        $columns = $table->getColumns();

        // 3. Target the subject column (usually named 'subject_type')
        $subjectColumnName = 'subject_type';

        if (isset($columns[$subjectColumnName])) {
            // 4. Inject your custom bilingual formatting!
            $columns[$subjectColumnName]->formatStateUsing(function ($record) {

                // Get Arabic Event
                $eventLabel = __('activitylog::action.event.'.$record->event);
                if ($eventLabel === 'activitylog::action.event.'.$record->event) {
                    $eventLabel = ucfirst($record->event);
                }

                // Get Bilingual Subject
                $subjectName = static::getSubjectBilingualLabel($record->subject_type);

                // Keep it clean with a short UUID
                $shortId = substr($record->subject_id, 0, 8);

                return "{$eventLabel} - {$subjectName} #{$shortId}";
            });
        }

        return $table;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivitylogs::route('/'),
            'view' => ViewActivitylog::route('/{record}'),
        ];
    }

    /**
     * Filter activity_log rows by the date the affected record itself belongs to
     * (student_entries.entry_time, or student_educations.start_date/end_date range),
     * instead of the log's created_at (when the edit happened).
     *
     * Combine with the built-in "event" filter (created/updated/deleted) to answer:
     * "what was added / deleted / changed regarding August's data?"
     */
    public static function getDataPeriodFilterComponent(): Filter
    {
        return Filter::make('data_period')
            ->label('الفترة الزمنية للبيانات المتأثرة')
            ->schema([
                DatePicker::make('period_from')->label('من تاريخ')->native(false),
                DatePicker::make('period_to')->label('إلى تاريخ')->native(false),
            ])
            ->indicateUsing(function (array $data): array {
                $indicators = [];

                if ($data['period_from'] ?? null) {
                    $indicators['period_from'] = 'بيانات من: '.$data['period_from'];
                }

                if ($data['period_to'] ?? null) {
                    $indicators['period_to'] = 'بيانات إلى: '.$data['period_to'];
                }

                return $indicators;
            })
            ->query(function (Builder $query, array $data): Builder {
                $from = $data['period_from'] ?? null;
                $to = $data['period_to'] ?? null;

                if (! $from && ! $to) {
                    return $query;
                }

                $timezone = config('app.timezone');
                $fromUtc = $from ? Carbon::parse($from, $timezone)->startOfDay()->utc() : null;
                $toUtc = $to ? Carbon::parse($to, $timezone)->endOfDay()->utc() : null;

                // entry_time_attr/entry_time_old/education_*_attr/education_*_old are
                // generated, indexed columns (see migration 2026_07_31_120000) that
                // pre-parse the relevant properties->... paths — filtering on them is
                // an index range scan instead of a JSON_EXTRACT/STR_TO_DATE full scan.
                return $query->where(function (Builder $q) use ($fromUtc, $toUtc) {
                    // Entries: entry_time falls in range, whether that value came from the
                    // new attributes (created/updated) or the old ones (deleted/updated).
                    $q->orWhere(function (Builder $q2) use ($fromUtc, $toUtc) {
                        $q2->where('subject_type', StudentEntry::class)
                            ->where(function (Builder $q3) use ($fromUtc, $toUtc) {
                                static::whereColumnBetween($q3, 'entry_time_attr', $fromUtc, $toUtc);
                                $q3->orWhere(function (Builder $q4) use ($fromUtc, $toUtc) {
                                    static::whereColumnBetween($q4, 'entry_time_old', $fromUtc, $toUtc);
                                });
                            });
                    });

                    // Educations: [start_date, end_date] range overlaps the requested period,
                    // whether that range came from the new attributes or the old ones.
                    $q->orWhere(function (Builder $q2) use ($fromUtc, $toUtc) {
                        $q2->where('subject_type', StudentEducation::class)
                            ->where(function (Builder $q3) use ($fromUtc, $toUtc) {
                                static::whereRangeOverlaps($q3, 'education_start_attr', 'education_end_attr', $fromUtc, $toUtc);
                                $q3->orWhere(function (Builder $q4) use ($fromUtc, $toUtc) {
                                    static::whereRangeOverlaps($q4, 'education_start_old', 'education_end_old', $fromUtc, $toUtc);
                                });
                            });
                    });
                });
            });
    }

    /**
     * Single source of truth for the bilingual subject label, shared by the
     * "subject_type" column formatting and the subject-type filter below.
     */
    public static function getSubjectBilingualLabel(?string $subjectType): string
    {
        return match ($subjectType) {
            StudentEntry::class => 'Entry | دخولية',
            Student::class => 'Student | طالب',
            StudentEducation::class => 'Education | تعليم الطالب',
            Education::class => 'Education Type | نوع التعليم',
            Center::class => 'Center | مركز',
            City::class => 'City | مدينة',
            Street::class => 'Street | شارع',
            User::class => 'User | مستخدم',
            default => $subjectType ? class_basename($subjectType) : '-',
        };
    }

    /**
     * Filter activity_log rows by which model the change belongs to (subject_type),
     * e.g. only "StudentEntry" rows, or only "StudentEducation" rows.
     */
    public static function getSubjectTypeFilterComponent(): SelectFilter
    {
        return SelectFilter::make('subject_type')
            ->label('الموضوع (نوع السجل)')
            ->options(fn () => static::getModel()::query()
                ->select('subject_type')
                ->whereNotNull('subject_type')
                ->distinct()
                ->pluck('subject_type', 'subject_type')
                ->mapWithKeys(fn ($value, $key) => [$key => static::getSubjectBilingualLabel($key)]));
    }

    /**
     * Filter entries by which center they happened at. Backed by the indexed
     * center_id_attr/center_id_old generated columns (see migration 2026_07_31_130000)
     * instead of scanning the JSON `properties` column.
     */
    public static function getCenterFilterComponent(): SelectFilter
    {
        return SelectFilter::make('center_id')
            ->label('المركز')
            ->options(fn () => Center::query()->pluck('name', 'id'))
            ->query(function (Builder $query, array $data): Builder {
                $centerId = $data['value'] ?? null;

                if (! $centerId) {
                    return $query;
                }

                return $query
                    ->where('subject_type', StudentEntry::class)
                    ->where(function (Builder $q) use ($centerId) {
                        $q->where('center_id_attr', $centerId)
                            ->orWhere('center_id_old', $centerId);
                    });
            });
    }

    /**
     * Detect entries whose claimed "entry_time" doesn't match the moment the row
     * was actually inserted (activity_log.created_at for the "created" event) —
     * e.g. an entry added today but dated back to June, which silently inflates
     * an already-submitted report for a past month.
     */
    public static function getRegistrationMismatchFilterComponent(): Filter
    {
        return Filter::make('registration_mismatch')
            ->label('عدم تطابق تاريخ الدخولية مع تاريخ إدخالها الفعلي')
            ->schema([
                TextInput::make('min_gap_days')
                    ->label('أقل فارق بين تاريخ الدخولية ووقت إنشائها (بالأيام)')
                    ->helperText('اتركه فارغًا لعدم تطبيق تدقيق الإنشاء')
                    ->numeric()
                    ->minValue(0),
                Select::make('direction')
                    ->label('اتجاه فارق الإنشاء')
                    ->native(false)
                    ->options([
                        'backdated' => 'تسجيل بأثر رجعي (تاريخ الدخولية أقدم من تاريخ الإدخال الفعلي)',
                        'postdated' => 'تسجيل مسبق (تاريخ الدخولية أحدث من تاريخ الإدخال الفعلي)',
                        'any' => 'أي فارق (بالاتجاهين)',
                    ])
                    ->default('backdated'),
            ])
            ->indicateUsing(function (array $data): array {
                $minDays = $data['min_gap_days'] ?? null;

                if ($minDays === null || $minDays === '') {
                    return [];
                }

                $directionLabel = match ($data['direction'] ?? 'backdated') {
                    'postdated' => 'مسبق',
                    'any' => 'بالاتجاهين',
                    default => 'رجعي',
                };

                return ['registration_mismatch' => "فارق تسجيل ({$directionLabel}) \u{2265} {$minDays} يوم"];
            })
            ->query(function (Builder $query, array $data): Builder {
                $minDays = $data['min_gap_days'] ?? null;

                if ($minDays === null || $minDays === '') {
                    return $query;
                }

                $minDays = (int) $minDays;
                $direction = $data['direction'] ?? 'backdated';

                static::useSubjectEventIndexHint($query);

                // entry_time_attr is a generated, indexed column (see migration
                // 2026_07_31_120000) that pre-parses properties->attributes->entry_time —
                // reading it directly is what makes this filter fast instead of running
                // JSON_EXTRACT/STR_TO_DATE against 180k+ rows on every request.
                $query = $query
                    ->where('subject_type', StudentEntry::class)
                    ->where('event', 'created')
                    ->whereNotNull('entry_time_attr');

                return match ($direction) {
                    'postdated' => $query->whereRaw('DATEDIFF(entry_time_attr, created_at) >= ?', [$minDays]),
                    'any' => $query->whereRaw('ABS(DATEDIFF(created_at, entry_time_attr)) >= ?', [$minDays]),
                    default => $query->whereRaw('DATEDIFF(created_at, entry_time_attr) >= ?', [$minDays]),
                };
            });
    }

    /**
     * "فارق التسجيل" — signed day gap between the entry's claimed entry_time and the
     * moment its activity_log row was actually written. Positive = backdated
     * (entered today for a past date), negative = postdated, '-' for non-entry rows.
     *
     * Only meaningful for the "created" event: on "updated" rows, entry_time_attr is
     * just the entry's *current* value regardless of whether this particular edit
     * touched it, so comparing it to the edit's own created_at produced misleading
     * numbers there. Use getEntryTimeEditColumnComponent()/Filter for edits instead.
     */
    public static function getRegistrationGapColumnComponent(): TextColumn
    {
        return TextColumn::make('registration_gap_days')
            ->label('فارق التسجيل عند الإنشاء (أيام)')
            ->state(function ($record) {
                if ($record->subject_type !== StudentEntry::class
                    || $record->event !== 'created'
                    || ! $record->entry_time_attr
                    || ! $record->created_at) {
                    return null;
                }

                $entryTime = Carbon::parse($record->entry_time_attr)->startOfDay();
                $loggedAt = $record->created_at->copy()->startOfDay();

                return $loggedAt->diffInDays($entryTime, absolute: false) * -1;
            })
            ->formatStateUsing(fn (?int $state) => $state === null ? '-' : ($state > 0 ? "+{$state}" : (string) $state))
            ->color(fn (?int $state) => match (true) {
                $state === null => 'gray',
                $state >= 7 => 'danger',
                $state >= 1 => 'warning',
                $state <= -1 => 'info',
                default => 'success',
            })
            ->badge()
            ->sortable(query: function (Builder $query, string $direction): Builder {
                return $query->orderByRaw(
                    "(CASE WHEN event = 'created' THEN DATEDIFF(created_at, entry_time_attr) ELSE NULL END) {$direction}"
                );
            })
            ->toggleable();
    }

    /**
     * "تعديل تاريخ الدخولية" — for "updated" events where entry_time itself actually
     * changed (not just some unrelated field), shows the shift: old value -> new value.
     * Answers "was entry_time edited, and by how much/how long after the fact?".
     */
    public static function getEntryTimeEditColumnComponent(): TextColumn
    {
        return TextColumn::make('entry_time_edit')
            ->label('تعديل تاريخ الدخولية')
            ->state(function ($record) {
                if ($record->subject_type !== StudentEntry::class
                    || $record->event !== 'updated'
                    || ! $record->entry_time_attr
                    || ! $record->entry_time_old
                    || $record->entry_time_attr === $record->entry_time_old) {
                    return null;
                }

                $old = Carbon::parse($record->entry_time_old);
                $new = Carbon::parse($record->entry_time_attr);
                $shiftDays = $old->copy()->startOfDay()->diffInDays($new->copy()->startOfDay(), absolute: false) * -1;

                return sprintf(
                    '%s ← %s (%s%d يوم)',
                    $old->format('Y-m-d'),
                    $new->format('Y-m-d'),
                    $shiftDays > 0 ? '+' : '',
                    $shiftDays
                );
            })
            ->color(fn (?string $state) => $state === null ? 'gray' : 'warning')
            ->badge()
            ->toggleable();
    }

    /**
     * Filter for "updated" activity_log rows where entry_time was actually edited
     * (not just some unrelated field on the entry) — optionally requiring a minimum
     * shift in the date itself, and/or a minimum delay between the entry_time it used
     * to claim and the moment someone came back and changed it. That delay is exactly
     * what flags "was this date quietly edited well after the fact".
     */
    public static function getEntryTimeEditFilterComponent(): Filter
    {
        return Filter::make('entry_time_edited')
            ->label('تعديل على تاريخ الدخولية')
            ->schema([
                Checkbox::make('enabled')
                    ->label('عرض أحداث التعديل التي غيّرت تاريخ الدخولية فقط')
                    ->default(false),
                TextInput::make('min_shift_days')
                    ->label('أقل تغيير في تاريخ الدخولية (بالأيام)')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('min_delay_days')
                    ->label('أقل مدة بين التاريخ القديم ووقت التعديل (بالأيام)')
                    ->helperText('اتركه فارغًا لعدم اشتراط مدة قبل التعديل')
                    ->numeric()
                    ->minValue(0),
            ])
            ->indicateUsing(function (array $data): array {
                if (! ($data['enabled'] ?? false)) {
                    return [];
                }

                $indicators = ['entry_time_edited' => 'تعديل على تاريخ الدخولية فقط'];

                if (($data['min_shift_days'] ?? null) !== null && $data['min_shift_days'] !== '') {
                    $indicators['min_shift_days'] = "الإزاحة \u{2265} {$data['min_shift_days']} يوم";
                }

                if (($data['min_delay_days'] ?? null) !== null && $data['min_delay_days'] !== '') {
                    $indicators['min_delay_days'] = "بعد \u{2265} {$data['min_delay_days']} يوم من التاريخ القديم";
                }

                return $indicators;
            })
            ->query(function (Builder $query, array $data): Builder {
                // Must be explicitly enabled — this filter forces event='updated', which
                // would otherwise silently contradict any other event the user picks
                // (this is exactly what broke the "created"/"deleted" event filter before).
                if (! ($data['enabled'] ?? false)) {
                    return $query;
                }

                static::useSubjectEventIndexHint($query);

                $query = $query
                    ->where('subject_type', StudentEntry::class)
                    ->where('event', 'updated')
                    ->whereNotNull('entry_time_attr')
                    ->whereNotNull('entry_time_old')
                    ->whereColumn('entry_time_attr', '<>', 'entry_time_old');

                $minShift = $data['min_shift_days'] ?? null;
                if ($minShift !== null && $minShift !== '') {
                    $query->whereRaw('ABS(DATEDIFF(entry_time_attr, entry_time_old)) >= ?', [(int) $minShift]);
                }

                $minDelay = $data['min_delay_days'] ?? null;
                if ($minDelay !== null && $minDelay !== '') {
                    // How long after the OLD claimed entry_time did this edit happen —
                    // e.g. an entry dated June, quietly moved to July, edited in October.
                    $query->whereRaw('DATEDIFF(created_at, entry_time_old) >= ?', [(int) $minDelay]);
                }

                return $query;
            });
    }

    /**
     * "فارق اليوم بين حدث الحذف وتاريخ الدخولية" — signed gap between when an entry
     * was actually deleted (activity_log.created_at for the "deleted" event) and the
     * entry_time it claimed. A large positive gap means an old entry was removed long
     * after the fact — e.g. deleting a March entry today quietly changes a report that
     * was already submitted for March.
     */
    public static function getDeletionGapFilterComponent(): Filter
    {
        return Filter::make('deletion_gap')
            ->label('فارق بين تاريخ الحذف وتاريخ الدخولية')
            ->schema([
                TextInput::make('min_gap_days')
                    ->label('أقل فارق بين تاريخ الدخولية ووقت حذفها (بالأيام)')
                    ->helperText('اتركه فارغًا لعدم تطبيق تدقيق الحذف')
                    ->numeric()
                    ->minValue(0),
                Select::make('direction')
                    ->label('اتجاه فارق الحذف')
                    ->native(false)
                    ->options([
                        'after_entry' => 'حُذفت بعد تاريخ الدخولية بفترة (الأكثر أهمية)',
                        'before_entry' => 'حُذفت قبل تاريخ الدخولية (نادر)',
                        'any' => 'أي فارق (بالاتجاهين)',
                    ])
                    ->default('after_entry'),
            ])
            ->indicateUsing(function (array $data): array {
                $minDays = $data['min_gap_days'] ?? null;

                if ($minDays === null || $minDays === '') {
                    return [];
                }

                $directionLabel = match ($data['direction'] ?? 'after_entry') {
                    'before_entry' => 'قبل تاريخ الدخولية',
                    'any' => 'بالاتجاهين',
                    default => 'بعد تاريخ الدخولية',
                };

                return ['deletion_gap' => "فارق الحذف ({$directionLabel}) \u{2265} {$minDays} يوم"];
            })
            ->query(function (Builder $query, array $data): Builder {
                $minDays = $data['min_gap_days'] ?? null;

                if ($minDays === null || $minDays === '') {
                    return $query;
                }

                $minDays = (int) $minDays;
                $direction = $data['direction'] ?? 'after_entry';

                static::useSubjectEventIndexHint($query);

                $query = $query
                    ->where('subject_type', StudentEntry::class)
                    ->where('event', 'deleted')
                    ->whereNotNull('entry_time_old');

                return match ($direction) {
                    'before_entry' => $query->whereRaw('DATEDIFF(entry_time_old, created_at) >= ?', [$minDays]),
                    'any' => $query->whereRaw('ABS(DATEDIFF(created_at, entry_time_old)) >= ?', [$minDays]),
                    default => $query->whereRaw('DATEDIFF(created_at, entry_time_old) >= ?', [$minDays]),
                };
            });
    }

    /**
     * "فارق الحذف (أيام)" — signed day gap between a deleted entry's claimed entry_time
     * and the moment it was actually deleted. Positive = the entry_time was in the past
     * relative to the deletion (deleted long after the fact). '-' for non-deleted rows.
     */
    public static function getDeletionGapColumnComponent(): TextColumn
    {
        return TextColumn::make('deletion_gap_days')
            ->label('فارق الحذف (أيام)')
            ->state(function ($record) {
                if ($record->subject_type !== StudentEntry::class
                    || $record->event !== 'deleted'
                    || ! $record->entry_time_old
                    || ! $record->created_at) {
                    return null;
                }

                $entryTime = Carbon::parse($record->entry_time_old)->startOfDay();
                $deletedAt = $record->created_at->copy()->startOfDay();

                return $deletedAt->diffInDays($entryTime, absolute: false) * -1;
            })
            ->formatStateUsing(fn (?int $state) => $state === null ? '-' : ($state > 0 ? "+{$state}" : (string) $state))
            ->color(fn (?int $state) => match (true) {
                $state === null => 'gray',
                $state >= 30 => 'danger',
                $state >= 1 => 'warning',
                $state <= -1 => 'info',
                default => 'success',
            })
            ->badge()
            ->sortable(query: function (Builder $query, string $direction): Builder {
                return $query->orderByRaw(
                    "(CASE WHEN event = 'deleted' THEN DATEDIFF(created_at, entry_time_old) ELSE NULL END) {$direction}"
                );
            })
            ->toggleable();
    }

    private static function whereColumnBetween(Builder $query, string $column, ?Carbon $from, ?Carbon $to): void
    {
        $query->where(function (Builder $q) use ($column, $from, $to) {
            $q->whereNotNull($column);

            if ($from) {
                $q->where($column, '>=', $from);
            }

            if ($to) {
                $q->where($column, '<=', $to);
            }
        });
    }

    private static function whereRangeOverlaps(Builder $query, string $startColumn, string $endColumn, ?Carbon $from, ?Carbon $to): void
    {
        $query->where(function (Builder $q) use ($startColumn, $endColumn, $from, $to) {
            $q->whereNotNull($startColumn);

            if ($to) {
                $q->where($startColumn, '<=', $to);
            }

            if ($from) {
                $q->where(function (Builder $q2) use ($endColumn, $from) {
                    $q2->whereNull($endColumn)->orWhere($endColumn, '>=', $from);
                });
            }
        });
    }

    /**
     * MySQL's optimizer misjudges the cost of `activity_log_subject_type_event_index`
     * against the older morph `subject` index (cardinality stats on this table are
     * skewed — only ~8 distinct subject_type values across 300k+ rows), and picks the
     * slower one, turning a sub-second index lookup into a multi-second scan. Forcing
     * the composite index for the specific subject_type+event queries we control here
     * is safe since we know exactly what those queries filter on.
     */
    private static function useSubjectEventIndexHint(Builder $query): void
    {
        $model = $query->getModel();
        $table = DB::connection($model->getConnectionName())->getTablePrefix().$model->getTable();

        $query->getQuery()->from = DB::raw("`{$table}` USE INDEX (activity_log_subject_type_event_index)");
    }
}
