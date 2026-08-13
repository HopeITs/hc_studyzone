@php
    $record = $getRecord();
    $state = $getState() ?? [];

    // Extract attributes (new values) and old (previous values)
    $attributes = $state['attributes'] ?? [];
    $old = $state['old'] ?? [];

    // Extract clean model name
    $subjectType = class_basename($record->subject_type);
    $event = $record->event;
    $data = $event === 'deleted' ? $old : $attributes;

    // --- RELATIONSHIP HELPERS ---
    // Note: Assuming your models have a 'name' column.
    // If your database uses 'title' or 'ar_name', change the ->name part below!

    $getStudent = function ($id) {
        if (!$id) {
            return 'N/A';
        }
        $student = \App\Models\Student::find($id);
        return $student
            ? $student->firstname . ' ' . $student->father_name . ' ' . $student->lastname
            : 'Unknown Student';
    };

    $getCenter = function ($id) {
        if (!$id) {
            return 'N/A';
        }
        $center = \App\Models\Center::find($id);
        return $center ? $center->name : 'Unknown Center';
    };

    $getCity = function ($id) {
        if (!$id) {
            return 'N/A';
        }
        $city = \App\Models\City::find($id);
        return $city ? $city->name : 'Unknown City';
    };

    $getEducation = function ($id) {
        if (!$id) {
            return 'N/A';
        }
        $education = \App\Models\Education::find($id); // Adjust model name if needed
        return $education ? $education->name : 'Unknown Education';
    };

    // All of the student's education/specialization records, with a flag on the
    // one whose [start_date, end_date] range actually covers this entry's entry_time
    // (this is exactly the range EntryStats.php uses to decide "is this a dentistry entry").
    $getStudentEducations = function ($studentId, $entryTime = null) {
        if (!$studentId) {
            return collect();
        }

        $entry = $entryTime ? \Illuminate\Support\Carbon::parse($entryTime) : null;

        return \App\Models\StudentEducation::where('student_id', $studentId)
            ->with('education')
            ->get()
            ->map(function ($studentEducation) use ($entry) {
                $covers = $entry
                    && $studentEducation->start_date
                    && $entry->gte($studentEducation->start_date)
                    && (is_null($studentEducation->end_date) || $entry->lte($studentEducation->end_date));

                return [
                    'name' => $studentEducation->education->name ?? 'Unknown',
                    'start' => $studentEducation->start_date?->format('Y-m-d') ?? '؟',
                    'end' => $studentEducation->end_date?->format('Y-m-d') ?? 'مستمر',
                    'covers' => $covers,
                ];
            });
    };

    $getEntryTypeLabel = function ($value) {
        if ($value === null || $value === '') {
            return 'N/A';
        }
        $label = __('general.entry-types.' . (int) $value);
        return $label === 'general.entry-types.' . (int) $value ? $value : $label;
    };

    // On "updated" events, flag fields that actually changed between old and new so
    // we can show "old -> new" instead of just the current value — most important for
    // entry_time/center_id/entry_type since those silently change what a past report counted.
    $isChanged = function ($key) use ($event, $attributes, $old) {
        return $event === 'updated'
            && array_key_exists($key, $attributes)
            && array_key_exists($key, $old)
            && $attributes[$key] !== $old[$key];
    };

    // Helper to resolve IDs in the fallback loop
    $resolveValue = function ($key, $value) use ($getStudent, $getCenter, $getCity, $getEducation) {
        if (!$value) {
            return 'N/A';
        }
        return match ($key) {
            'student_id' => $getStudent($value),
            'center_id' => $getCenter($value),
            'city_id' => $getCity($value),
            'education_id' => $getEducation($value),
            default => is_array($value) ? json_encode($value) : $value,
        };
    };

    // Fields to hide
    $ignoredFields = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
        'synced_at',
        'use_drawer',
        'use_printer',
        'use_plotter',
        'use_mecrator',
        'use_internet',
        'password',
        'remember_token',
        'email_verified_at'
    ];
@endphp

<div class="my-2 text-sm tracking-tight">

    {{-- 1. Status Badge --}}
    <div class="mb-2">
        @php
            $badgeColors = [
                'created' => 'bg-green-500/10 text-green-700 dark:text-green-400',
                'updated' => 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
                'deleted' => 'bg-red-500/10 text-red-700 dark:text-red-400',
            ];
            $color = $badgeColors[$event] ?? 'bg-gray-500/10 text-gray-700 dark:text-gray-400';
        @endphp
        {{-- <span class="inline-block px-2 py-1 font-medium rounded-md whitespace-normal {{ $color }}">
            {{ ucfirst($event) }} {{ $subjectType }}
        </span> --}}
    </div>

    {{-- 2. Specific Formatting for Core Models --}}
    <div class="p-3 rounded-sm bg-gray-50 dark:bg-gray-800 ">
        @if ($subjectType === 'Student' && !empty($data))
            <div class="flex-col items-start justify-start">
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Name:</span>
                    <strong class="text-gray-900 dark:text-gray-100">{{ $data['firstname'] ?? '' }}
                        {{ $data['father_name'] ?? '' }} {{ $data['lastname'] ?? '' }}</strong>
                </div>
            </div>
        @elseif($subjectType === 'StudentEntry' && !empty($data))
            <div class="flex-col items-start justify-start">
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Date:</span>
                    @if ($isChanged('entry_time'))
                        <strong class="text-amber-700 dark:text-amber-400">
                            {{ date('Y-m-d H:i', strtotime($old['entry_time'])) }}
                            <span class="mx-1">→</span>
                            {{ date('Y-m-d H:i', strtotime($attributes['entry_time'])) }}
                        </strong>
                    @else
                        <strong
                            class="text-gray-900 dark:text-gray-100">{{ isset($data['entry_time']) ? date('Y-m-d H:i', strtotime($data['entry_time'])) : 'N/A' }}</strong>
                    @endif
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Center:</span>
                    @if ($isChanged('center_id'))
                        <strong class="text-amber-700 dark:text-amber-400">
                            {{ $getCenter($old['center_id']) }}
                            <span class="mx-1">→</span>
                            {{ $getCenter($attributes['center_id']) }}
                        </strong>
                    @else
                        <strong
                            class="text-gray-900 dark:text-gray-100">{{ isset($data['center_id']) ? $getCenter($data['center_id']) : 'N/A' }}</strong>
                    @endif
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">نوع الدخولية:</span>
                    @if ($isChanged('entry_type'))
                        <strong class="text-amber-700 dark:text-amber-400">
                            {{ $getEntryTypeLabel($old['entry_type'] ?? null) }}
                            <span class="mx-1">→</span>
                            {{ $getEntryTypeLabel($attributes['entry_type'] ?? null) }}
                        </strong>
                    @else
                        <strong
                            class="text-gray-900 dark:text-gray-100">{{ $getEntryTypeLabel($data['entry_type'] ?? null) }}</strong>
                    @endif
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Student:</span>
                    <strong
                        class="text-gray-900 dark:text-gray-100">{{ isset($data['student_id']) ? $getStudent($data['student_id']) : 'N/A' }}</strong>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">دراسة الطالب:</span>
                    @php
                        $studentEducations = isset($data['student_id'])
                            ? $getStudentEducations($data['student_id'], $data['entry_time'] ?? null)
                            : collect();
                    @endphp
                    @if ($studentEducations->isEmpty())
                        <strong class="text-gray-900 dark:text-gray-100">لا يوجد</strong>
                    @else
                        <div class="mt-0.5 space-y-0.5">
                            @foreach ($studentEducations as $edu)
                                <div
                                    class="{{ $edu['covers'] ? 'font-semibold text-green-700 dark:text-green-400' : 'text-gray-700 dark:text-gray-300' }}">
                                    {{ $edu['name'] }} ({{ $edu['start'] }} → {{ $edu['end'] }})
                                    @if ($edu['covers'])
                                        <span class="text-xs">✓ يشمل تاريخ الدخولية</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @elseif($subjectType === 'StudentEducation' && !empty($data))
            <div class="flex-col items-start justify-start">
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Education:</span>
                    <strong
                        class="text-gray-900 dark:text-gray-100">{{ isset($data['education_id']) ? $getEducation($data['education_id']) : 'N/A' }}</strong>
                </div>
                <div class="sm:col-span-2">
                    <span class="text-gray-500 dark:text-gray-400">Student:</span>
                    <strong
                        class="text-gray-900 dark:text-gray-100">{{ isset($data['student_id']) ? $getStudent($data['student_id']) : 'N/A' }}</strong>
                </div>
            </div>
        @else
            {{-- 3. Fallback: Clean Generic List with Auto-Resolving IDs --}}
            <div class="flex-col items-start justify-start">
                @foreach ($data as $key => $val)
                    @if (!in_array($key, $ignoredFields))
                        <div class="truncate">
                            {{-- Strip out '_id' from the label for cleaner reading --}}
                            <span
                                class="text-gray-500 capitalize dark:text-gray-400">{{ str_replace(['_id', '_'], ['', ' '], $key) }}:</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">
                                {{ $resolveValue($key, $val) }}
                            </span>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>

</div>
