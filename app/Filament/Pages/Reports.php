<?php

namespace App\Filament\Pages;

use App\Enums\Roles;
use App\Models\Center;
use App\Models\City;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Reports extends Page
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $slug = 'reports';

    protected static ?int $navigationSort = 50;

    protected static string $view = 'filament.pages.reports';

    public static function getNavigationLabel(): string
    {
        return __('general.navigation.reports');
    }

    public function getTitle(): string
    {
        return __('general.navigation.reports');
    }

    public ?array $data = [
        'city_id' => null,
        'center_id' => [],
        'from' => null,
        'to' => null,
    ];

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user && in_array($user->role, [
            Roles::GeneralAdmin(),
            Roles::CityAdmin(),
            Roles::CenterAdmin(),
            Roles::MealAdmin(),
            Roles::Observer(),
        ], true);
    }

    public function mount(): void
    {
        $this->form->fill([
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Select::make('city_id')
                            ->label(__('general.labels.city'))
                            ->options(City::pluck('name', 'id'))
                            ->live()
                            ->visible(auth()->user()->role === Roles::GeneralAdmin() || auth()->user()->role === Roles::Observer())
                            ->afterStateUpdated(fn (callable $set) => $set('center_id', []))
                            ->columnSpanFull(),

                        Select::make('center_id')
                            ->label(__('general.labels.centers'))
                            ->options(function (callable $get) {
                                $user = auth()->user();
                                $cityId = $get('city_id');

                                if ($user->role === Roles::CityAdmin() || $user->role === Roles::MealAdmin()) {
                                    return Center::where('city_id', $user->city_id)->orderBy('order')
                                        ->pluck('name', 'id');
                                }

                                if ($user->role === Roles::CenterAdmin()) {
                                    return Center::where('id', $user->center_id)->orderBy('order')
                                        ->pluck('name', 'id');
                                }

                                if ($user->role === Roles::Observer()) {
                                    if ($cityId) {
                                        return Center::where('city_id', $cityId)->orderBy('order')
                                            ->pluck('name', 'id');
                                    }

                                    return Center::orderBy('order')
                                        ->pluck('name', 'id');
                                }

                                if ($cityId) {
                                    return Center::where('city_id', $cityId)->orderBy('order')
                                        ->pluck('name', 'id');
                                }

                                return Center::orderBy('order')
                                    ->pluck('name', 'id');
                            })
                            ->multiple()
                            ->searchable()
                            ->columnSpanFull()
                            ->hidden(auth()->user()->role === Roles::CenterAdmin())
                            ->default(function () {
                                $user = auth()->user();

                                return $user->role === Roles::CenterAdmin() ? [$user->center_id] : [];
                            }),

                        DatePicker::make('from')
                            ->label(__('general.labels.from'))
                            ->default(now()->startOfMonth())
                            ->required()
                            ->columnSpan(1),

                        DatePicker::make('to')
                            ->label(__('general.labels.to'))
                            ->default(now()->endOfMonth())
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

       protected function getActions(): array
    {
        return [
            Action::make('export_students_by_entry_period')
                ->label(__('general.labels.export_students_period'))
                ->color('primary')
                ->action(function (): StreamedResponse {
                    // 1. Increase execution time for this specific script only
                    set_time_limit(300); // 5 minutes

                    $state = $this->form->getState();

                    $from = $state['from'];
                    $to = $state['to'];
                    $cityId = $state['city_id'] ?? auth()->user()->city_id;
                    $centerIds = $state['center_id'] ?? [];

                    $query = Student::query()
                        // 2. FIX: Eager load relationships to prevent N+1 queries inside the loop
                        // We load studentEducations and their related education model NOW
                        ->with(['street.city', 'studentEducations.education'])
                        ->whereHas('entries', function (Builder $q) use ($from, $to, $cityId, $centerIds) {
                            if ($from) {
                                $q->whereDate('entry_time', '>=', $from);
                            }
                            if ($to) {
                                $q->whereDate('entry_time', '<=', $to);
                            }
                            if (!empty($centerIds)) {
                                $q->whereIn('center_id', $centerIds);
                            } elseif ($cityId) {
                                $q->whereHas('center', function ($qq) use ($cityId) {
                                    $qq->where('city_id', $cityId);
                                });
                            }
                        })
                        ->withCount([
                            'entries as entries_in_period_count' => function (Builder $q) use ($from, $to, $cityId, $centerIds) {
                                if ($from) {
                                    $q->whereDate('entry_time', '>=', $from);
                                }
                                if ($to) {
                                    $q->whereDate('entry_time', '<=', $to);
                                }
                                if (!empty($centerIds)) {
                                    $q->whereIn('center_id', $centerIds);
                                } elseif ($cityId) {
                                    $q->whereHas('center', function ($qq) use ($cityId) {
                                        $qq->where('city_id', $cityId);
                                    });
                                }
                            },
                        ]);

                    $subquery = "
                        (
                            SELECT GROUP_CONCAT(
                                DISTINCT JSON_UNQUOTE(JSON_EXTRACT(c.name, '$.ar'))
                                ORDER BY JSON_UNQUOTE(JSON_EXTRACT(c.name, '$.ar'))
                                SEPARATOR ', '
                            )
                            FROM hcsz_centers c
                            INNER JOIN hcsz_student_entries e ON c.id = e.center_id
                            WHERE e.student_id = hcsz_students.id
                            " . ($from ? "AND DATE(e.entry_time) >= '$from' " : '') .
                        ($to ? "AND DATE(e.entry_time) <= '$to' " : '') .
                        (!empty($centerIds) ? 'AND e.center_id IN (' . implode(',', $centerIds) . ')' : '') .
                        "
                        ) AS center_names
                    ";

                    $query->addSelect(DB::raw($subquery));
                    $query = $this->applyRoleScope($query);

                    // 3. FIX: Use lazy() instead of get().
                    // This keeps memory usage low by loading one student at a time instead of all at once.
                    $students = $query->orderBy('firstname')->orderBy('father_name')->orderBy('lastname')->lazy();

                    $headers = [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                        'Content-Disposition' => 'attachment; filename="students_report_' . $from . '_to_' . $to . '.csv"',
                        'Cache-Control' => 'no-store, no-cache',
                    ];

                    return response()->streamDownload(
                        function () use ($students) {
                            $out = fopen('php://output', 'w');
                            fwrite($out, chr(0xef) . chr(0xbb) . chr(0xbf)); // UTF-8 BOM
                            fputcsv($out, ['الاسم الثلاثي', 'اسم المركز', 'تاريخ الميلاد', 'الرقم الوطني/الإقامة', 'رقم الهاتف/واتساب', 'عدد الدخوليات', 'الديانة', 'الاختصاص', 'مستوى التعليم', 'مكان الإقامة', 'الجنس', 'الحالة (طالب/موظف)', 'تاريخ الإنشاء']);

                            foreach ($students as $s) {
                                // 4. FIX: Access already loaded data instead of querying DB again
                                $fullName = trim($s->firstname . ' ' . $s->father_name . ' ' . $s->lastname);
                                $centerName = $s->center_names ?? '';
                                $dob = $s->year_of_birth ? (string) $s->year_of_birth : '';
                                $idNum = $s->national_number ?: ($s->foreign_id_number ?: '');
                                $phoneWhats = trim(($s->phone ?? '') . ($s->phone && $s->whatsapp ? ' / ' : '') . ($s->whatsapp ?? ''));
                                $entriesCount = (int) ($s->entries_in_period_count ?? 0);
                                $religion = __('general.religions.' . (string) $s->religion);

                                $specialization = '-';
                                // We use the collection loaded in memory via eager load
                                $sortedEducations = $s->studentEducations->sortByDesc('start_date');
                                $recentEducations = $sortedEducations->take(3);

                                if ($recentEducations->isNotEmpty()) {
                                    $names = $recentEducations->map(function($se) {
                                        return $se->education?->getTranslation('name', 'ar') ?? '';
                                    })->filter()->values();

                                    if ($names->isNotEmpty()) {
                                        $specialization = $names->implode(', ');
                                    }
                                }

                                $educationLevel = '-';
                                $lastEducation = $sortedEducations->first();

                                if ($lastEducation && $lastEducation->education) {
                                    $levelValue = $lastEducation->level;
                                    $educationLevel = __('general.edu-levels.' . (string) $levelValue);
                                }

                                $residence = ($s->street->city->name ?? '') . ' - ' . ($s->street->name ?? '');
                                $gender = __('general.genders.' . (string) $s->gender);
                                $statusParts = [];
                                if ($s->is_student) {
                                    $statusParts[] = __('general.labels.is_student');
                                }
                                if ($s->is_employed) {
                                    $statusParts[] = __('general.labels.is_employed');
                                }
                                $status = implode('/', $statusParts);
                                $created = $s->created_at ? $s->created_at->format('Y-m-d') : '';

                                fputcsv($out, [$fullName, $centerName, $dob, $idNum, $phoneWhats, $entriesCount, $religion, $specialization, $educationLevel, $residence, $gender, $status, $created]);
                            }

                            fclose($out);
                        },
                        null,
                        $headers,
                    );
                }),
        ];
    }
    protected function applyRoleScope(Builder $query): Builder
    {
        $user = Auth::user();

        if ($user->role === Roles::GeneralAdmin() || $user->role === Roles::Observer()) {
            return $query;
        }

        if ($user->role === Roles::CityAdmin() || $user->role === Roles::MealAdmin()) {
            return $query->whereHas('entries.center', fn ($q) => $q->where('city_id', $user->city_id)
            );
        }

        if ($user->role === Roles::CenterAdmin()) {
            return $query->whereHas('entries', fn ($q) => $q->where('center_id', $user->center_id)
            );
        }

        return $query->whereRaw('1 = 0');
    }
}
