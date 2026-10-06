<?php

namespace App\Filament\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use App\Enums\Roles;
use App\Models\Center;
use App\Models\City;
use App\Models\Education;
use App\Models\Street;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Reports extends Page
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $slug = 'reports';

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.pages.reports';

    public static function getNavigationLabel(): string
    {
        return __('general.navigation.reports');
    }

    public function getTitle(): string
    {
        return __('general.navigation.reports');
    }

    public ?array $data = [
        'search' => null,
        'city_id' => null,
        'street_id' => [],
        'center_id' => [],
        'from' => null,
        'to' => null,
        'data_status' => null,
        'education_id' => [],
        'degree' => [],
        'education_level' => [],
        'gender' => null,
        'religion' => null,
        'national_number_status' => null,
        'skip_national_number' => null,
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
        $this->form->fill($this->defaultFilterValues());
    }

    protected function defaultFilterValues(): array
    {
        return [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('search')
                            ->label(__('general.labels.search_name_or_phone'))
                            ->placeholder(__('general.labels.search_name_or_phone'))
                            ->columnSpan(1),

                        Select::make('city_id')
                            ->label(__('general.labels.city'))
                            ->options(City::pluck('name', 'id'))
                            ->live()
                            ->visible(auth()->user()->role === Roles::GeneralAdmin() || auth()->user()->role === Roles::Observer())
                            ->afterStateUpdated(function (callable $set) {
                                $set('center_id', []);
                                $set('street_id', []);
                            })
                            ->columnSpan(1),

                        Select::make('street_id')
                            ->label(__('general.labels.street'))
                            ->options(function (callable $get) {
                                $user = auth()->user();
                                $cityId = $get('city_id');

                                if ($user->role === Roles::CityAdmin() || $user->role === Roles::MealAdmin()) {
                                    return Street::where('city_id', $user->city_id)->pluck('name', 'id');
                                }

                                if ($user->role === Roles::CenterAdmin()) {
                                    return Street::where('city_id', $user->city_id)->pluck('name', 'id');
                                }

                                if ($cityId) {
                                    return Street::where('city_id', $cityId)->pluck('name', 'id');
                                }

                                return Street::pluck('name', 'id');
                            })
                            ->multiple()
                            ->searchable()
                            ->default([])
                             ->columnSpan(1),

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
                           ->columnSpan(1)
                            ->hidden(auth()->user()->role === Roles::CenterAdmin())
                            ->default(function () {
                                $user = auth()->user();

                                return $user->role === Roles::CenterAdmin() ? [$user->center_id] : [];
                            }),

                        Select::make('data_status')
                            ->label(__('general.labels.data_status'))
                            ->options([
                                'completed' => __('general.labels.completed_data'),
                                'incomplete' => __('general.labels.incomplete_data'),
                            ])
                            ->placeholder(__('general.labels.all'))
                            ->columnSpan(1),

                        Select::make('national_number_status')
                            ->label(__('general.labels.national_number_or_residency'))
                            ->options([
                                'exists' => __('general.labels.exists'),
                                'not_exists' => __('general.labels.not_exists'),
                            ])
                            ->placeholder(__('general.labels.all'))
                            ->columnSpan(1),

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

                        Select::make('education_id')
                            ->label(__('general.labels.education_plural'))
                            ->options(fn () => Education::all()->pluck('name', 'id'))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->default([])
                            ->columnSpan(1),

                        Select::make('degree')
                            ->label(__('general.labels.degree'))
                            ->options(__('general.edu-degrees'))
                            ->multiple()
                            ->searchable()
                            ->default([])
                            ->columnSpan(1),

                        Select::make('education_level')
                            ->label(__('general.labels.education_level'))
                            ->options(__('general.edu-levels'))
                            ->multiple()
                            ->searchable()
                            ->default([])
                            ->columnSpan(1),

                        Select::make('gender')
                            ->label(__('general.labels.gender'))
                            ->options(__('general.genders'))
                            ->placeholder(__('general.labels.all'))
                            ->columnSpan(1),

                        Select::make('religion')
                            ->label(__('general.labels.religion'))
                            ->options(__('general.religions'))
                            ->placeholder(__('general.labels.all'))
                            ->columnSpan(1),

                        Select::make('skip_national_number')
                            ->label(__('general.labels.skip_national_number'))
                            ->options([
                                '1' => __('general.labels.yes'),
                                '0' => __('general.labels.no'),
                            ])
                            ->placeholder(__('general.labels.all'))
                            ->columnSpan(1),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    /**
     * Ordered map of exportable column key => [label, value resolver].
     * The value resolver receives the Student model and the (already sorted)
     * studentEducations collection so it isn't re-sorted per column.
     */
    protected function exportColumnDefinitions(): array
    {
        return [
            'full_name' => [
                'label' => 'الاسم الثلاثي',
                'value' => fn (Student $s) => trim($s->firstname.' '.$s->father_name.' '.$s->lastname),
            ],
            'center_name' => [
                'label' => 'اسم المركز',
                'value' => fn (Student $s) => $s->center_names ?? '',
            ],
            'dob' => [
                'label' => 'تاريخ الميلاد',
                'value' => fn (Student $s) => $s->year_of_birth ? (string) $s->year_of_birth : '',
            ],
            'national_id' => [
                'label' => 'الرقم الوطني/الإقامة',
                'value' => fn (Student $s) => $s->national_number ?: ($s->foreign_id_number ?: ''),
            ],
            'phone' => [
                'label' => 'رقم الهاتف/واتساب',
                'value' => fn (Student $s) => trim(($s->phone ?? '').($s->phone && $s->whatsapp ? ' / ' : '').($s->whatsapp ?? '')),
            ],
            'entries_count' => [
                'label' => 'عدد الدخوليات',
                'value' => fn (Student $s) => (int) ($s->entries_in_period_count ?? 0),
            ],
            'religion' => [
                'label' => 'الديانة',
                'value' => fn (Student $s) => __('general.religions.'.(string) $s->religion),
            ],
            'specialization' => [
                'label' => 'الاختصاص',
                'value' => function (Student $s, $sortedEducations) {
                    $recentEducations = $sortedEducations->take(3);

                    if ($recentEducations->isEmpty()) {
                        return '-';
                    }

                    $names = $recentEducations->map(fn ($se) => $se->education?->getTranslation('name', 'ar') ?? '')->filter()->values();

                    return $names->isNotEmpty() ? $names->implode(', ') : '-';
                },
            ],
            'education_level' => [
                'label' => 'مستوى التعليم',
                'value' => function (Student $s, $sortedEducations) {
                    $lastEducation = $sortedEducations->first();

                    if ($lastEducation && $lastEducation->education) {
                        return __('general.edu-levels.'.(string) $lastEducation->level);
                    }

                    return '-';
                },
            ],
            'residence' => [
                'label' => 'مكان الإقامة',
                'value' => fn (Student $s) => ($s->street->city->name ?? '').' - '.($s->street->name ?? ''),
            ],
            'gender' => [
                'label' => 'الجنس',
                'value' => fn (Student $s) => __('general.genders.'.(string) $s->gender),
            ],
            'status' => [
                'label' => 'الحالة (طالب/موظف)',
                'value' => function (Student $s) {
                    $parts = [];
                    if ($s->is_student) {
                        $parts[] = __('general.labels.is_student');
                    }
                    if ($s->is_employed) {
                        $parts[] = __('general.labels.is_employed');
                    }

                    return implode('/', $parts);
                },
            ],
            'created_at' => [
                'label' => 'تاريخ الإنشاء',
                'value' => fn (Student $s) => $s->created_at ? $s->created_at->format('Y-m-d') : '',
            ],
        ];
    }

    protected function getActions(): array
    {
        $columnDefinitions = $this->exportColumnDefinitions();

        return [
            Action::make('reset_filters')
                ->label(__('general.labels.reset_filters'))
                ->color('danger')
                ->action(fn () => $this->form->fill($this->defaultFilterValues())),

            Action::make('export_students_by_entry_period')
                ->label(__('general.labels.export_students_period'))
                ->color('primary')
                ->schema([
                    CheckboxList::make('columns')
                        ->label(__('general.labels.columns_to_export'))
                        ->options(collect($columnDefinitions)->map(fn (array $def) => $def['label'])->toArray())
                        ->default(array_keys($columnDefinitions))
                        ->bulkToggleable()
                        ->columns(2)
                        ->required(),
                ])
                ->modalHeading(__('general.labels.export_columns_modal_heading'))
                ->modalSubmitActionLabel(__('general.labels.confirm_export'))
                ->action(function (array $data) use ($columnDefinitions): StreamedResponse {
                    // 1. Increase execution time for this specific script only
                    set_time_limit(300); // 5 minutes

                    $state = $this->form->getState();

                    $from = $state['from'];
                    $to = $state['to'];
                    $cityId = $state['city_id'] ?? auth()->user()->city_id;
                    $centerIds = $state['center_id'] ?? [];
                    $streetIds = $state['street_id'] ?? [];
                    $dataStatus = $state['data_status'] ?? null;
                    $educationIds = $state['education_id'] ?? [];
                    $degrees = $state['degree'] ?? [];
                    $educationLevels = $state['education_level'] ?? [];
                    $gender = $state['gender'] ?? null;
                    $religion = $state['religion'] ?? null;
                    $nationalNumberStatus = $state['national_number_status'] ?? null;
                    $skipNationalNumber = $state['skip_national_number'] ?? null;
                    $search = $state['search'] ?? null;

                    // Selected columns for export, kept in the fixed canonical order
                    $selectedColumns = array_values(array_intersect(
                        array_keys($columnDefinitions),
                        $data['columns'] ?? array_keys($columnDefinitions)
                    ));

                    if (empty($selectedColumns)) {
                        $selectedColumns = array_keys($columnDefinitions);
                    }

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
                            if (! empty($centerIds)) {
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
                                if (! empty($centerIds)) {
                                    $q->whereIn('center_id', $centerIds);
                                } elseif ($cityId) {
                                    $q->whereHas('center', function ($qq) use ($cityId) {
                                        $qq->where('city_id', $cityId);
                                    });
                                }
                            },
                        ]);

                    if (! empty($streetIds)) {
                        $query->whereIn('street_id', $streetIds);
                    }

                    if ($dataStatus === 'completed') {
                        $query->where('completed_data', true);
                    } elseif ($dataStatus === 'incomplete') {
                        $query->where('completed_data', false);
                    }

                    if (! empty($educationIds)) {
                        $query->whereHas('studentEducations', function (Builder $q) use ($educationIds) {
                            $q->whereIn('education_id', $educationIds);
                        });
                    }

                    if (! empty($degrees)) {
                        $query->whereHas('educations', function (Builder $q) use ($degrees) {
                            $q->whereIn('degree', $degrees);
                        });
                    }

                    if (! empty($educationLevels)) {
                        $query->whereHas('studentEducations', function (Builder $q) use ($educationLevels) {
                            $q->whereIn('level', $educationLevels);
                        });
                    }

                    if ($gender !== null && $gender !== '') {
                        $query->where('gender', (int) $gender);
                    }

                    if ($religion !== null && $religion !== '') {
                        $query->where('religion', (int) $religion);
                    }

                    if ($nationalNumberStatus === 'exists') {
                        $query->whereNotNull('national_number');
                    } elseif ($nationalNumberStatus === 'not_exists') {
                        $query->whereNull('national_number');
                    }

                    if ($skipNationalNumber !== null && $skipNationalNumber !== '') {
                        $query->where('skip_national_number', (bool) (int) $skipNationalNumber);
                    }

                    if (! empty($search)) {
                        $query->where(function (Builder $q) use ($search) {
                            $q->where('firstname', 'like', "%{$search}%")
                                ->orWhere('father_name', 'like', "%{$search}%")
                                ->orWhere('lastname', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%")
                                ->orWhere('whatsapp', 'like', "%{$search}%");
                        });
                    }

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
                            ".($from ? "AND DATE(e.entry_time) >= '$from' " : '').
                        ($to ? "AND DATE(e.entry_time) <= '$to' " : '').
                        (! empty($centerIds) ? 'AND e.center_id IN ('.implode(',', $centerIds).')' : '').
                        '
                        ) AS center_names
                    ';

                    $query->addSelect(DB::raw($subquery));
                    $query = $this->applyRoleScope($query);

                    // 3. FIX: Use lazy() instead of get().
                    // This keeps memory usage low by loading one student at a time instead of all at once.
                    $students = $query->orderBy('firstname')->orderBy('father_name')->orderBy('lastname')->lazy();

                    $headers = [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                        'Content-Disposition' => 'attachment; filename="students_report_'.$from.'_to_'.$to.'.csv"',
                        'Cache-Control' => 'no-store, no-cache',
                    ];

                    return response()->streamDownload(
                        function () use ($students, $columnDefinitions, $selectedColumns) {
                            $out = fopen('php://output', 'w');
                            fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

                            fputcsv($out, array_map(fn ($key) => $columnDefinitions[$key]['label'], $selectedColumns));

                            foreach ($students as $s) {
                                // 4. FIX: Access already loaded data instead of querying DB again
                                $sortedEducations = $s->studentEducations->sortByDesc('start_date');

                                $row = array_map(
                                    fn ($key) => $columnDefinitions[$key]['value']($s, $sortedEducations),
                                    $selectedColumns
                                );

                                fputcsv($out, $row);
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
