<?php

namespace App\Filament\Resources;

use App\Enums\Roles;
use App\Models\Center;
use App\Models\Student;
use App\Models\StudentEntry;
use Carbon\Carbon;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * A focused, manager-facing view answering one question: "did an entry get
 * added / edited / deleted well after the date it claims to represent?" —
 * built on top of the same indexed snapshot columns as CustomActivitylogResource
 * (see migrations 2026_07_31_120000/130000), but stripped down to a single
 * table with one metric instead of the many separate developer-oriented
 * filters over there.
 */
class EntryAuditResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationLabel = 'تتبع تعديلات الدخوليات';

    protected static ?string $modelLabel = 'حدث على دخولية';

    protected static ?string $pluralModelLabel = 'أحداث على الدخوليات';

    protected static ?string $slug = 'entry-audit';

    protected static ?string $navigationGroup = 'إدارة النظام';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('subject_type', StudentEntry::class)
            ->with('causer');
    }

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()->role, [Roles::GeneralAdmin(), Roles::Observer()], true);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading('متابعة الفارق الزمني بين تاريخ الدخولية وتاريخ الحدث')
            ->description('يعرض كل إضافة/تعديل/حذف على الدخوليات، مع الفارق بالأيام بين تاريخ الدخولية والوقت الذي حدث فيه الإجراء فعلياً — الأكبر فارقاً أولاً.')
            ->columns([
                static::getEventColumn(),
                static::getEntryDateColumn(),
                static::getEventDateColumn(),
                static::getGapColumn(),
                static::getStudentColumn(),
                static::getCenterColumn(),
                static::getCauserColumn(),
                static::getChangeDetailsColumn(),
            ])
            ->filters([
                static::getEventTypeFilter(),
                static::getMinGapFilter(),
                static::getEntryPeriodFilter(),
                static::getCenterFilter(),
            ])
            ->defaultSort('gap_days', 'desc')
            ->striped()
            ->paginated([25, 50, 100]);
    }

    // ---------------------------------------------------------------
    // Columns
    // ---------------------------------------------------------------

    private static function getEventColumn(): TextColumn
    {
        return TextColumn::make('event')
            ->label('الإجراء')
            ->badge()
            ->formatStateUsing(fn (string $state) => match ($state) {
                'created' => 'إضافة',
                'updated' => 'تعديل',
                'deleted' => 'حذف',
                default   => $state,
            })
            ->color(fn (string $state) => match ($state) {
                'created' => 'success',
                'updated' => 'warning',
                'deleted' => 'danger',
                default   => 'gray',
            });
    }

    private static function getEntryDateColumn(): TextColumn
    {
        return TextColumn::make('entry_date')
            ->label('تاريخ الدخولية')
            ->state(fn ($record) => static::resolveEntryDate($record)?->format('Y-m-d H:i') ?? '-');
    }

    private static function getEventDateColumn(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label('تاريخ الإجراء')
            ->dateTime('Y-m-d H:i')
            ->sortable();
    }

    /**
     * The core metric: DATEDIFF(event time, entry date). Positive = the entry's date
     * was in the past relative to when the action happened (added/edited/deleted long
     * after the fact) — exactly the pattern that silently changes an already-submitted
     * report. COALESCE(entry_time_attr, entry_time_old) picks the right snapshot column
     * for every event type: attr covers created & updated, old covers deleted.
     */
    private static function getGapColumn(): TextColumn
    {
        return TextColumn::make('gap_days')
            ->label('الفارق الزمني (أيام)')
            ->state(function ($record) {
                $entryDate = static::resolveEntryDate($record);

                if (! $entryDate || ! $record->created_at) {
                    return null;
                }

                return $record->created_at->copy()->startOfDay()->diffInDays($entryDate->copy()->startOfDay(), absolute: false) * -1;
            })
            ->formatStateUsing(fn (?int $state) => $state === null ? '-' : ($state > 0 ? "+{$state}" : (string) $state))
            ->color(fn (?int $state) => match (true) {
                $state === null => 'gray',
                $state >= 14    => 'danger',
                $state >= 1     => 'warning',
                $state <= -1    => 'info',
                default         => 'success',
            })
            ->weight('bold')
            ->badge()
            ->sortable(query: function (Builder $query, string $direction): Builder {
                return $query->orderByRaw(
                    'DATEDIFF(created_at, COALESCE(entry_time_attr, entry_time_old)) ' . $direction
                );
            });
    }

    private static function getStudentColumn(): TextColumn
    {
        return TextColumn::make('student')
            ->label('الطالب')
            ->state(function ($record) {
                $studentId = $record->properties['attributes']['student_id']
                    ?? $record->properties['old']['student_id']
                    ?? null;

                if (! $studentId) {
                    return '-';
                }

                $student = Student::find($studentId);

                return $student
                    ? trim("{$student->firstname} {$student->father_name} {$student->lastname}")
                    : '-';
            });
    }

    private static function getCenterColumn(): TextColumn
    {
        return TextColumn::make('center')
            ->label('المركز')
            ->state(function ($record) {
                $centerId = $record->center_id_attr ?? $record->center_id_old ?? null;

                if (! $centerId) {
                    return '-';
                }

                return Center::find($centerId)?->name ?? '-';
            });
    }

    private static function getCauserColumn(): TextColumn
    {
        return TextColumn::make('causer.name')
            ->label('من قام بالإجراء')
            ->default('-');
    }

    /**
     * For "updated" rows only: a compact "old -> new" summary limited to the three
     * fields that matter for report accuracy (date/center/type), mirroring the
     * detailed diff already shown in the Activity Log's properties view.
     */
    private static function getChangeDetailsColumn(): TextColumn
    {
        return TextColumn::make('change_details')
            ->label('التفاصيل')
            ->wrap()
            ->state(function ($record) {
                if ($record->event !== 'updated') {
                    return '-';
                }

                $attributes = $record->properties['attributes'] ?? [];
                $old        = $record->properties['old'] ?? [];
                $parts      = [];

                if (($attributes['entry_time'] ?? null) !== ($old['entry_time'] ?? null) && isset($attributes['entry_time'], $old['entry_time'])) {
                    $parts[] = 'التاريخ: ' . date('Y-m-d H:i', strtotime($old['entry_time'])) . ' ← ' . date('Y-m-d H:i', strtotime($attributes['entry_time']));
                }

                if (($attributes['center_id'] ?? null) !== ($old['center_id'] ?? null) && isset($attributes['center_id'], $old['center_id'])) {
                    $oldCenter = Center::find($old['center_id'])?->name ?? '؟';
                    $newCenter = Center::find($attributes['center_id'])?->name ?? '؟';
                    $parts[]   = "المركز: {$oldCenter} ← {$newCenter}";
                }

                if (($attributes['entry_type'] ?? null) !== ($old['entry_type'] ?? null) && isset($attributes['entry_type'], $old['entry_type'])) {
                    $parts[] = 'النوع: ' . static::getEntryTypeLabel($old['entry_type']) . ' ← ' . static::getEntryTypeLabel($attributes['entry_type']);
                }

                return $parts === [] ? 'تعديل على حقل آخر' : implode(' | ', $parts);
            });
    }

    // ---------------------------------------------------------------
    // Filters
    // ---------------------------------------------------------------

    private static function getEventTypeFilter(): Filter
    {
        return Filter::make('event')
            ->label('نوع الإجراء')
            ->form([
                CheckboxList::make('events')
                    ->label('')
                    ->options([
                        'created' => 'إضافة',
                        'updated' => 'تعديل',
                        'deleted' => 'حذف',
                    ])
                    ->default(['created', 'updated', 'deleted'])
                    ->columns(3),
            ])
            ->query(function (Builder $query, array $data): Builder {
                $events = $data['events'] ?? [];

                // All 3 selected (or none touched) behaves the same as "no filter" —
                // only narrow the query when the user has actually excluded some.
                if (empty($events) || count($events) === 3) {
                    return $query;
                }

                return $query->whereIn('event', $events);
            });
    }

    private static function getMinGapFilter(): Filter
    {
        return Filter::make('min_gap')
            ->label('أقل فارق زمني (أيام)')
            ->form([
                TextInput::make('min_gap_days')
                    ->label('أظهر فقط الأحداث التي فارقها')
                    ->suffix('يوم فأكثر')
                    ->numeric()
                    ->minValue(0),
            ])
            ->indicateUsing(fn (array $data) => filled($data['min_gap_days'] ?? null)
                ? ["min_gap_days" => "الفارق \u{2265} {$data['min_gap_days']} يوم"]
                : [])
            ->query(function (Builder $query, array $data): Builder {
                $minDays = $data['min_gap_days'] ?? null;

                if ($minDays === null || $minDays === '') {
                    return $query;
                }

                return $query->whereRaw(
                    'ABS(DATEDIFF(created_at, COALESCE(entry_time_attr, entry_time_old))) >= ?',
                    [(int) $minDays]
                );
            });
    }

    private static function getEntryPeriodFilter(): Filter
    {
        return Filter::make('entry_period')
            ->label('فترة تاريخ الدخولية')
            ->form([
                DatePicker::make('from')->label('من')->native(false),
                DatePicker::make('to')->label('إلى')->native(false),
            ])
            ->indicateUsing(function (array $data): array {
                $indicators = [];

                if (filled($data['from'] ?? null)) {
                    $indicators['from'] = 'من: ' . $data['from'];
                }

                if (filled($data['to'] ?? null)) {
                    $indicators['to'] = 'إلى: ' . $data['to'];
                }

                return $indicators;
            })
            ->query(function (Builder $query, array $data): Builder {
                $from = $data['from'] ?? null;
                $to   = $data['to'] ?? null;

                if (! $from && ! $to) {
                    return $query;
                }

                $timezone = config('app.timezone');
                $fromUtc  = $from ? Carbon::parse($from, $timezone)->startOfDay()->utc() : null;
                $toUtc    = $to ? Carbon::parse($to, $timezone)->endOfDay()->utc() : null;

                return $query->where(function (Builder $q) use ($fromUtc, $toUtc) {
                    $q->where(function (Builder $q2) use ($fromUtc, $toUtc) {
                        $q2->whereNotNull('entry_time_attr');
                        if ($fromUtc) {
                            $q2->where('entry_time_attr', '>=', $fromUtc);
                        }
                        if ($toUtc) {
                            $q2->where('entry_time_attr', '<=', $toUtc);
                        }
                    })->orWhere(function (Builder $q2) use ($fromUtc, $toUtc) {
                        $q2->whereNull('entry_time_attr')->whereNotNull('entry_time_old');
                        if ($fromUtc) {
                            $q2->where('entry_time_old', '>=', $fromUtc);
                        }
                        if ($toUtc) {
                            $q2->where('entry_time_old', '<=', $toUtc);
                        }
                    });
                });
            });
    }

    private static function getCenterFilter(): SelectFilter
    {
        return SelectFilter::make('center_id')
            ->label('المركز')
            ->options(fn () => Center::query()->pluck('name', 'id'))
            ->query(function (Builder $query, array $data): Builder {
                $centerId = $data['value'] ?? null;

                if (! $centerId) {
                    return $query;
                }

                return $query->where(function (Builder $q) use ($centerId) {
                    $q->where('center_id_attr', $centerId)
                        ->orWhere('center_id_old', $centerId);
                });
            });
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private static function resolveEntryDate($record): ?Carbon
    {
        $value = $record->entry_time_attr ?? $record->entry_time_old ?? null;

        return $value ? Carbon::parse($value) : null;
    }

    private static function getEntryTypeLabel($value): string
    {
        if ($value === null || $value === '') {
            return 'N/A';
        }

        $label = __('general.entry-types.' . (int) $value);

        return $label === 'general.entry-types.' . (int) $value ? (string) $value : $label;
    }

    public static function getPages(): array
    {
        return [
            'index' => EntryAuditResource\Pages\ListEntryAudits::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
