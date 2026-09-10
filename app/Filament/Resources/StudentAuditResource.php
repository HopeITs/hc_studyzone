<?php

namespace App\Filament\Resources;

use App\Enums\Roles;
use App\Models\Street;
use App\Models\Student;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * Manager-facing audit trail for Student profile changes, mirroring
 * EntryAuditResource's simplicity. Unlike StudentEntry, a Student record has
 * no separate "claimed date" field distinct from created_at (no entry_time
 * equivalent) — there's nothing to compute a meaningful day-gap against — so
 * this page focuses on what actually matters here: *what* changed, on report-
 * relevant fields (name, nationality, national ID, active/student status),
 * and by whom, rather than a numeric gap.
 */
class StudentAuditResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'تتبع تعديلات الطلاب';

    protected static ?string $modelLabel = 'حدث على طالب';

    protected static ?string $pluralModelLabel = 'أحداث على الطلاب';

    protected static ?string $slug = 'student-audit';

    protected static ?string $navigationGroup = 'إدارة النظام';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('subject_type', Student::class)
            ->with('causer');
    }

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()->role, [Roles::GeneralAdmin(), Roles::Observer()], true);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading('متابعة إضافة وتعديل وحذف بيانات الطلاب')
            ->description('يعرض كل إضافة/تعديل/حذف على بيانات الطلاب الأساسية (الاسم، الجنسية، الرقم الوطني، الحالة)، مع تفاصيل ما تغيّر ومن قام به.')
            ->columns([
                static::getEventColumn(),
                static::getEventDateColumn(),
                static::getStudentColumn(),
                static::getStreetColumn(),
                static::getCauserColumn(),
                static::getChangeDetailsColumn(),
            ])
            ->filters([
                static::getEventTypeFilter(),
                static::getEventPeriodFilter(),
                static::getNameSearchFilter(),
            ])
            ->defaultSort('created_at', 'desc')
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

    private static function getEventDateColumn(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label('تاريخ الإجراء')
            ->dateTime('Y-m-d H:i')
            ->sortable();
    }

    private static function getStudentColumn(): TextColumn
    {
        return TextColumn::make('student_name')
            ->label('الطالب')
            ->state(function ($record) {
                $data = static::currentData($record);

                $name = trim(($data['firstname'] ?? '') . ' ' . ($data['father_name'] ?? '') . ' ' . ($data['lastname'] ?? ''));

                return $name !== '' ? $name : '-';
            });
    }

    private static function getStreetColumn(): TextColumn
    {
        return TextColumn::make('street')
            ->label('الشارع')
            ->state(function ($record) {
                $streetId = static::currentData($record)['street_id'] ?? null;

                return $streetId ? (Street::find($streetId)?->name ?? '-') : '-';
            });
    }

    private static function getCauserColumn(): TextColumn
    {
        return TextColumn::make('causer.name')
            ->label('من قام بالإجراء')
            ->default('-');
    }

    /**
     * For "updated" rows only: old -> new for the fields that actually affect who
     * counts in a report (identity, nationality, active/student status) — the same
     * kind of silent-drift risk as entry_time/center_id on the entries side.
     */
    private static function getChangeDetailsColumn(): TextColumn
    {
        $trackedFields = [
            'firstname'    => 'الاسم الأول',
            'father_name'  => 'اسم الأب',
            'lastname'     => 'العائلة',
            'nationality'  => 'الجنسية',
            'national_number' => 'الرقم الوطني',
            'phone'        => 'الهاتف',
            'is_active'    => 'نشط',
            'is_student'   => 'طالب',
        ];

        return TextColumn::make('change_details')
            ->label('التفاصيل')
            ->wrap()
            ->state(function ($record) use ($trackedFields) {
                if ($record->event !== 'updated') {
                    return '-';
                }

                $attributes = $record->properties['attributes'] ?? [];
                $old        = $record->properties['old'] ?? [];
                $parts      = [];

                foreach ($trackedFields as $key => $label) {
                    if (! array_key_exists($key, $attributes) || ! array_key_exists($key, $old)) {
                        continue;
                    }

                    if ($attributes[$key] === $old[$key]) {
                        continue;
                    }

                    $formatValue = fn ($value) => match (true) {
                        is_bool($value) => $value ? 'نعم' : 'لا',
                        $value === null || $value === '' => '(فارغ)',
                        default => (string) $value,
                    };

                    $parts[] = "{$label}: {$formatValue($old[$key])} ← {$formatValue($attributes[$key])}";
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

                if (empty($events) || count($events) === 3) {
                    return $query;
                }

                return $query->whereIn('event', $events);
            });
    }

    private static function getEventPeriodFilter(): Filter
    {
        return Filter::make('event_period')
            ->label('فترة تاريخ الإجراء')
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

                return $query
                    ->when($from, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                    ->when($to, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
            });
    }

    private static function getNameSearchFilter(): Filter
    {
        return Filter::make('name_search')
            ->label('بحث باسم الطالب')
            ->form([
                TextInput::make('name')->label('الاسم أو جزء منه'),
            ])
            ->indicateUsing(fn (array $data) => filled($data['name'] ?? null)
                ? ['name' => "الاسم يحتوي: {$data['name']}"]
                : [])
            ->query(function (Builder $query, array $data): Builder {
                $name = trim($data['name'] ?? '');

                if ($name === '') {
                    return $query;
                }

                return $query->where(function (Builder $q) use ($name) {
                    foreach (['firstname', 'father_name', 'lastname'] as $field) {
                        $q->orWhereRaw(
                            "JSON_UNQUOTE(JSON_EXTRACT(properties, '$.attributes.{$field}')) LIKE ?",
                            ["%{$name}%"]
                        )->orWhereRaw(
                            "JSON_UNQUOTE(JSON_EXTRACT(properties, '$.old.{$field}')) LIKE ?",
                            ["%{$name}%"]
                        );
                    }
                });
            });
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private static function currentData($record): array
    {
        if ($record->event === 'deleted') {
            return $record->properties['old'] ?? [];
        }

        return $record->properties['attributes'] ?? $record->properties['old'] ?? [];
    }

    public static function getPages(): array
    {
        return [
            'index' => StudentAuditResource\Pages\ListStudentAudits::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
