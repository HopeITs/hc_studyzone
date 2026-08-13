<div class="space-y-4" dir="rtl">
    @forelse ($years as $year)
        <x-filament::section
            :heading="$year"
            icon="heroicon-o-calendar-days"
            collapsible
            collapsed
            persist-collapsed
            id="month-locks-{{ $year }}"
        >
            <x-slot name="headerEnd">
                <label
                    class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer dark:text-gray-200"
                    x-on:click.stop
                >
                    <span>{{ __('general.labels.lock_all_months') }}</span>

                    <x-filament::input.checkbox
                        :checked="$fullyLockedYears[$year]"
                        wire:change="setYearLock({{ $year }}, $event.target.checked)"
                        wire:loading.attr="disabled"
                        wire:target="setYearLock"
                        aria-label="{{ __('general.labels.lock_all_months') }}: {{ $year }}"
                    />
                </label>
            </x-slot>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($monthsByYear[$year] as $month => $monthName)
                    @php($isLocked = isset($lockedPeriods["{$year}-{$month}"]))

                    <label
                        wire:key="month-lock-{{ $year }}-{{ $month }}"
                        @class([
                            'flex cursor-pointer items-center justify-between gap-3 rounded-lg border p-4 transition',
                            'border-danger-200 bg-danger-50 dark:border-danger-800 dark:bg-danger-950/30' => $isLocked,
                            'border-gray-200 bg-white hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10' => ! $isLocked,
                        ])
                    >
                        <span class="flex items-center gap-2">
                            <span class="font-medium text-gray-950 dark:text-white">
                                {{ $monthName }}
                            </span>

                            <x-filament::badge color="gray">
                                {{ $entryCounts["{$year}-{$month}"] ?? 0 }}
                                {{ __('general.labels.entries_count') }}
                            </x-filament::badge>
                        </span>

                        <x-filament::input.checkbox
                            :checked="$isLocked"
                            wire:change="setMonthLock({{ $year }}, {{ $month }}, $event.target.checked)"
                            wire:loading.attr="disabled"
                            wire:target="setMonthLock"
                            aria-label="{{ __('general.labels.lock_month') }}: {{ $monthName }} {{ $year }}"
                        />
                    </label>
                @endforeach
            </div>
        </x-filament::section>
    @empty
        <x-filament::section>
            <div class="py-8 text-sm text-center text-gray-500 dark:text-gray-400">
                {{ __('general.labels.no_entry_years') }}
            </div>
        </x-filament::section>
    @endforelse
</div>
