<x-filament-panels::page>
    @php
        $label = fn (string $key) => __('general.admission_preview.'.$key);
        $visits = collect($this->visits[$this->center] ?? [])->groupBy('type');
        $prices = $this->prices[$this->center][$this->detail] ?? [];
    @endphp

    <style>
        .admission-preview { display: grid; gap: 1rem; }
        .admission-preview .preview-fields { display: flex; flex-wrap: wrap; gap: .875rem; align-items: end; }
        .admission-preview .preview-field { display: grid; gap: .375rem; }
        .admission-preview .preview-field input:not([type=checkbox]),
        .admission-preview .preview-field select {
            border: 1px solid #71717a; border-radius: .5rem; padding: .45rem .65rem;
            color: inherit; background: transparent; min-height: 2.5rem;
        }
        .dark .admission-preview select { color-scheme: dark; }
        .admission-preview .preview-table { overflow-x: auto; }
        .admission-preview table { min-width: 32rem; width: 100%; text-align: start; }
        .admission-preview th, .admission-preview td {
            text-align: start; padding: .75rem; border-bottom: 1px solid rgb(128 128 128 / .25);
        }
        .admission-preview .preview-error { color: #dc2626; }
    </style>

    <div class="admission-preview">
        <div class="preview-fields">
            <x-filament::badge color="warning">{{ $label('demo') }}</x-filament::badge>
            <label class="preview-field">
                {{ $label('center') }}
                <select wire:model.live="center" wire:loading.attr="disabled">
                    @foreach ($this::CENTERS as $option)
                        <option value="{{ $option }}">{{ $label($option) }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        @if ($this->detail === '')
            <x-filament::section>
                <div class="preview-table">
                    <table>
                        <thead><tr>
                            <th scope="col">{{ $label('type') }}</th>
                            <th scope="col">{{ $label('count') }}</th>
                            <th scope="col">{{ $label('total') }}</th>
                            <th scope="col">{{ $label('details') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($this::TYPES as $type)
                                @php($rows = $visits->get($type, collect()))
                                <tr>
                                    <td>{{ $label($type) }}</td>
                                    <td>{{ $rows->count() }}</td>
                                    <td>{{ $this->money($rows->sum('amount')) }}</td>
                                    <td><x-filament::button size="sm" color="gray" wire:click="openDetail('{{ $type }}')">{{ $label('details') }}</x-filament::button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section :heading="$label('visit')">
                <form wire:submit="recordVisit" class="preview-fields">
                    <label class="preview-field">
                        {{ $label('type') }}
                        <select wire:model.live="mode">
                            @foreach ($this::TYPES as $type)
                                <option value="{{ $type }}">{{ $label($type) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="preview-field">
                        {{ $label('total') }}
                        <strong>{{ $this->money($this->mode === 'daily' ? $this->currentDailyPrice() : 0) }}</strong>
                    </div>
                    @if ($this->mode === 'monthly')
                        <x-filament::badge color="success">{{ $label('covered') }}</x-filament::badge>
                    @elseif ($this->mode === 'daily')
                        <label><input type="checkbox" wire:model="collect"> {{ $label('collect') }}</label>
                    @endif
                    <x-filament::button type="submit" wire:loading.attr="disabled">{{ $label('record') }}</x-filament::button>
                    @if ($this->recorded)
                        <span role="status">{{ $label('recorded') }}</span>
                    @endif
                </form>
            </x-filament::section>
        @else
            <div><x-filament::button color="gray" wire:click="closeDetail">{{ $label('back') }}</x-filament::button></div>
            <x-filament::section :heading="$label($this->detail)">
                @if ($this->detail === 'free')
                    <p>{{ $this->money(0) }}</p>
                @else
                    <div class="preview-fields">
                        <x-filament::button :color="$this->history ? 'gray' : 'primary'" wire:click="showHistory(false)">{{ $label('active_prices') }}</x-filament::button>
                        <x-filament::button :color="$this->history ? 'primary' : 'gray'" wire:click="showHistory(true)">{{ $label('history') }}</x-filament::button>
                        <x-filament::button color="gray" wire:click="openSchedule">{{ $label('schedule') }}</x-filament::button>
                    </div>
                    <div class="preview-table">
                        <table>
                            <thead><tr>
                                @foreach (['center', 'amount', 'start', 'end', 'status'] as $column)
                                    <th scope="col">{{ $label($column) }}</th>
                                @endforeach
                            </tr></thead>
                            <tbody>
                                @foreach (array_reverse($prices) as $price)
                                    @php($status = $this->status($price))
                                    @continue(! $this->history && $status !== 'active')
                                    <tr>
                                        <td>{{ $label($this->center) }}</td>
                                        <td>{{ $this->money($price['amount']) }}</td>
                                        <td><bdi>{{ $price['start'] }}</bdi></td>
                                        <td><bdi>{{ $price['end'] ?? $label('open') }}</bdi></td>
                                        <td><x-filament::badge :color="$status === 'active' ? 'success' : ($status === 'scheduled' ? 'warning' : 'gray')">{{ $label($status) }}</x-filament::badge></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>

            @if ($this->scheduling)
                <x-filament::section :heading="$label('schedule')">
                    <form wire:submit="schedule" class="preview-fields">
                        <label class="preview-field">
                            {{ $label('amount') }} ({{ $this->currency() }})
                            <input type="number" min="1" step="1" wire:model="amount" required>
                            @error('amount') <span class="preview-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="preview-field">
                            {{ $label('start') }}
                            <input type="date" wire:model="start" required>
                            @error('start') <span class="preview-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="preview-field">
                            {{ $label('end') }}
                            <input type="date" wire:model="end">
                            @error('end') <span class="preview-error">{{ $message }}</span> @enderror
                        </label>
                        <x-filament::button type="submit" wire:loading.attr="disabled">{{ $label('save') }}</x-filament::button>
                    </form>
                </x-filament::section>
            @endif
        @endif
    </div>
</x-filament-panels::page>
