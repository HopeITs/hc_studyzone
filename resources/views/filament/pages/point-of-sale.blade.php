<x-filament-panels::page>
    <style>
        .sz-pos { container-type: inline-size; }
        .sz-pos-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; align-items: start; }
        .sz-pos-grid > *, .sz-pos-cards > *, .sz-pos-pair > * { min-width: 0; }
        .sz-pos-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 9rem), 1fr)); gap: .75rem; }
        .sz-pos-pair { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 9rem), 1fr)); gap: .5rem; }
        .sz-pos-line { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
        .sz-pos-line-name { flex: 1 1 8rem; min-width: 0; }
        .sz-pos-wrap { overflow-wrap: anywhere; }
        .sz-pos-student-result { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: .25rem .75rem; }
        @container (min-width: 43rem) {
            .sz-pos-grid { grid-template-columns: minmax(0, 1.5fr) minmax(21rem, 1fr); }
        }
    </style>
    <div class="sz-pos">
    <div class="sz-pos-grid">
        {{-- Catalog --}}
        <div class="space-y-4">
            @if ($this->isGeneralAdmin())
                <x-filament::section>
                    <label for="pos-center" class="mb-2 block text-sm font-medium text-gray-950 dark:text-white">
                        {{ __('pos.fields.center') }}
                    </label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="pos-center" wire:model.live="centerId">
                            <option value="">{{ __('pos.messages.select_center') }}</option>
                            @foreach ($this->centers as $center)
                                <option value="{{ $center->id }}">{{ $center->name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </x-filament::section>
            @endif

            @if ($this->effectiveCenterId())
                {{-- Cash shift status --}}
                @if (! $this->openShift)
                    <div class="flex flex-wrap items-center gap-2 rounded-xl bg-danger-50 p-3 text-sm font-semibold text-danger-700 ring-1 ring-danger-300 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-500/30">
                        <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
                        <span class="flex-1">{{ __('finance.validation.no_open_shift') }}</span>
                        <a
                            href="{{ \App\Filament\Resources\CashShiftResource::getUrl() }}"
                            class="shrink-0 underline hover:no-underline"
                        >
                            {{ __('finance.cash_shifts') }}
                        </a>
                    </div>
                @else
                    <div class="flex items-center gap-2 rounded-xl bg-[#023D55]/5 px-3 py-2 text-xs font-medium text-[#023D55] ring-1 ring-[#023D55]/20 dark:bg-[#F99E1E]/10 dark:text-[#F99E1E] dark:ring-[#F99E1E]/30">
                        <x-heroicon-o-check-circle class="h-4 w-4 shrink-0" />
                        <span>
                            {{ __('finance.shift_status.open') }}
                            — {{ __('finance.labels.opened_at') }}: {{ $this->openShift->opened_at->format('Y-m-d H:i') }}
                        </span>
                    </div>
                @endif

                <x-filament::section>
                    <div class="space-y-4">
                        {{-- Search --}}
                        <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                            <x-filament::input
                                type="search"
                                wire:model.live.debounce.400ms="search"
                                placeholder="{{ __('pos.catalog.search_placeholder') }}"
                                aria-label="{{ __('pos.catalog.search_placeholder') }}"
                            />
                        </x-filament::input.wrapper>

                        {{-- Category tabs --}}
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                wire:click="$set('activeTab', 'all')"
                                @class([
                                    'rounded-full px-3 py-1.5 text-sm font-medium transition',
                                    'bg-[#023D55] text-white dark:bg-[#F99E1E] dark:text-[#023D55]' => $this->activeTab === 'all',
                                    'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10' => $this->activeTab !== 'all',
                                ])
                            >
                                {{ __('pos.catalog.all') }}
                            </button>

                            @foreach (\App\Filament\Pages\PointOfSale::CATEGORY_TABS as $tab)
                                <button
                                    type="button"
                                    wire:click="$set('activeTab', @js($tab))"
                                    @class([
                                        'rounded-full px-3 py-1.5 text-sm font-medium transition',
                                        'bg-[#023D55] text-white dark:bg-[#F99E1E] dark:text-[#023D55]' => $this->activeTab === $tab,
                                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10' => $this->activeTab !== $tab,
                                    ])
                                >
                                    {{ $tab }}
                                </button>
                            @endforeach
                        </div>

                        {{-- Cards --}}
                        @if ($this->catalog->isEmpty())
                            <p class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ __('pos.catalog.empty') }}
                            </p>
                        @else
                            <div class="sz-pos-cards">
                                @foreach ($this->catalog as $card)
                                    <button
                                        type="button"
                                        wire:click="addToCart(@js($card['key']))"
                                        wire:key="card-{{ $card['key'] }}"
                                        class="flex flex-col items-start gap-1 rounded-xl border border-gray-200 bg-white p-3 text-start transition hover:border-[#023D55] hover:shadow dark:border-white/10 dark:bg-white/5 dark:hover:border-[#F99E1E]"
                                    >
                                        <span class="sz-pos-wrap w-full text-sm font-semibold text-gray-950 dark:text-white">
                                            {{ $card['name'] }}
                                        </span>

                                        <span class="text-sm font-bold text-[#023D55] dark:text-[#F99E1E]">
                                            {{ number_format($card['unit_price']) }} SYP
                                        </span>

                                        @if ($card['kind'] === 'inventory')
                                            <x-filament::badge :color="$card['stock'] <= 0 ? 'gray' : ($card['stock'] <= $card['threshold'] ? 'primary' : 'secondary')">
                                                {{ __('pos.catalog.stock') }}: {{ $card['stock'] }}
                                            </x-filament::badge>
                                        @else
                                            <x-filament::badge color="secondary">
                                                {{ __('invoices.kinds.service') }}
                                            </x-filament::badge>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </x-filament::section>
            @else
                <x-filament::section>
                    <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ __('pos.messages.select_center') }}
                    </p>
                </x-filament::section>
            @endif
        </div>

        {{-- Invoice draft --}}
        <div class="space-y-4">
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('pos.cart.title') }}
                </x-slot>

                <div class="space-y-4">
                    @if (empty($this->cart))
                        <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                            {{ __('pos.cart.empty') }}
                        </p>
                    @else
                        <ul class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($this->cart as $key => $line)
                                <li class="sz-pos-line py-2" wire:key="line-{{ $key }}">
                                    <div class="sz-pos-line-name">
                                        <p class="sz-pos-wrap text-sm font-medium text-gray-950 dark:text-white">
                                            {{ $line['name'] }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ number_format($line['unit_price']) }} SYP
                                        </p>
                                    </div>

                                    <div class="w-20 shrink-0">
                                        <x-filament::input.wrapper>
                                            <x-filament::input
                                                type="number"
                                                min="1"
                                                wire:model.lazy="cart.{{ $key }}.quantity"
                                                wire:change="normalizeQuantity(@js($key))"
                                                class="text-center"
                                                aria-label="{{ __('invoices.fields.quantity') }}"
                                            />
                                        </x-filament::input.wrapper>
                                    </div>

                                    <span class="shrink-0 text-end text-sm font-semibold text-gray-950 dark:text-white">
                                        {{ number_format(max(1, (int) $line['quantity']) * (int) $line['unit_price']) }}
                                    </span>

                                    <x-filament::icon-button
                                        icon="heroicon-o-trash"
                                        color="danger"
                                        wire:click="removeLine(@js($key))"
                                        :label="__('pos.cart.remove')"
                                    />
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    {{-- Beneficiary --}}
                    <div class="space-y-3">
                        <fieldset class="min-w-0 space-y-2">
                            <legend class="text-sm font-medium text-gray-950 dark:text-white">{{ __('pos.fields.beneficiary_type') }}</legend>
                            <div class="sz-pos-pair">
                                @foreach (['walk_in', 'student'] as $type)
                                    <button
                                        type="button"
                                        wire:click="chooseBeneficiaryType(@js($type))"
                                        aria-pressed="{{ $beneficiaryType === $type ? 'true' : 'false' }}"
                                        @class([
                                            'min-h-10 rounded-lg border px-3 py-2 text-sm font-medium',
                                            'border-[#023D55] bg-[#023D55] text-white dark:border-[#F99E1E] dark:bg-[#F99E1E] dark:text-[#023D55]' => $beneficiaryType === $type,
                                            'border-gray-200 text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5' => $beneficiaryType !== $type,
                                        ])
                                    >
                                        {{ __('pos.fields.'.$type) }}
                                    </button>
                                @endforeach
                            </div>
                        </fieldset>
                        @if ($beneficiaryType === 'student')
                            <div>
                                <label for="pos-student-search" class="mb-2 block text-sm font-medium text-gray-950 dark:text-white">
                                    {{ __('pos.fields.student_search') }}
                                </label>

                                @if ($studentId)
                                    <div class="flex items-center justify-between gap-2 rounded-lg border border-[#023D55]/40 bg-[#023D55]/5 px-3 py-2 text-sm dark:border-[#F99E1E]/40 dark:bg-[#F99E1E]/10">
                                        <span class="sz-pos-wrap min-w-0 font-medium text-[#023D55] dark:text-[#F99E1E]">
                                            {{ $beneficiaryName }}
                                        </span>
                                        <button
                                            type="button"
                                            wire:click="clearStudent"
                                            class="text-gray-400 hover:text-danger-500"
                                            title="{{ __('pos.actions.clear_student') }}"
                                        >
                                            &times;
                                        </button>
                                    </div>
                                @if ($this->selectedStudentHasVisit === false)
                                    <div class="space-y-2 rounded-lg border border-gray-200 px-3 py-2 dark:border-white/10" role="status">
                                        <x-filament::badge color="warning">
                                            {{ __('pos.messages.student_has_no_visit') }}
                                        </x-filament::badge>
                                        @if ($visitUrl = $this->registerVisitUrl)
                                            <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('pos.messages.register_visit_hint') }}</p>
                                            <div class="flex flex-wrap gap-2">
                                                <x-filament::button tag="a" :href="$visitUrl" target="_blank" rel="noopener">
                                                    {{ __('pos.actions.register_visit') }}
                                                </x-filament::button>
                                                <x-filament::button color="gray" wire:click="$refresh">
                                                    {{ __('pos.actions.refresh_visit') }}
                                                </x-filament::button>
                                            </div>
                                        @else
                                            <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('pos.messages.visit_registration_unavailable') }}</p>
                                        @endif
                                    </div>
                                @elseif ($this->selectedStudentHasVisit === true)
                                    <x-filament::badge color="success">{{ __('pos.messages.student_has_visit') }}</x-filament::badge>
                                @endif
                                @else
                                    <x-filament::input.wrapper prefix-icon="heroicon-m-user">
                                        <x-filament::input
                                            id="pos-student-search"
                                            type="text"
                                            maxlength="120"
                                            wire:model.live.debounce.400ms="studentSearch"
                                            placeholder="{{ __('pos.fields.student_search_placeholder') }}"
                                        />
                                    </x-filament::input.wrapper>

                                    @php($studentResults = $this->studentResults)

                                    @if ($studentResults->isNotEmpty())
                                        <ul class="mt-2 divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white dark:divide-white/10 dark:border-white/10 dark:bg-gray-900">
                                            @foreach ($studentResults as $result)
                                                <li>
                                                    <button
                                                        type="button"
                                                        wire:click="selectStudent('{{ $result['id'] }}')"
                                                        class="sz-pos-student-result w-full px-3 py-2 text-start text-sm hover:bg-gray-50 dark:hover:bg-white/5"
                                                    >
                                                        <span class="sz-pos-wrap min-w-0 text-gray-950 dark:text-white">{{ $result['name'] }}</span>
                                                        @if ($result['phone'])
                                                            <span class="text-xs text-gray-400">{{ $result['phone'] }}</span>
                                                        @endif
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @elseif (mb_strlen(trim($studentSearch)) >= 2)
                                        <p class="mt-1 text-xs text-gray-400">{{ __('pos.messages.no_students_found') }}</p>
                                    @endif
                                    @if ($studentResults->count() === 12)
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('pos.messages.refine_student_search') }}</p>
                                    @endif
                                @endif
                            </div>

                        @else
                            <div>
                                <label for="pos-beneficiary" class="mb-2 block text-sm font-medium text-gray-950 dark:text-white">
                                    {{ __('pos.fields.optional_beneficiary_name') }}
                                </label>
                                <x-filament::input.wrapper>
                                    <x-filament::input
                                        id="pos-beneficiary"
                                        type="text"
                                        maxlength="255"
                                        wire:model="beneficiaryName"
                                    />
                                </x-filament::input.wrapper>
                                @error('beneficiaryName')
                                    <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        <details wire:ignore.self class="rounded-lg border border-gray-200 px-3 py-2 dark:border-white/10">
                            <summary class="cursor-pointer text-sm font-medium text-gray-950 dark:text-white">{{ __('pos.fields.additional_details') }}</summary>
                            <div class="mt-3 space-y-3">
                                @if ($beneficiaryType === 'student')
                                <div>
                                    <label for="pos-student-number" class="mb-2 block text-sm font-medium text-gray-950 dark:text-white">
                                        {{ __('invoices.fields.student_number') }}
                                    </label>
                                    <x-filament::input.wrapper>
                                        <x-filament::input
                                            id="pos-student-number"
                                            type="text"
                                            wire:model="studentNumber"
                                        />
                                    </x-filament::input.wrapper>
                                </div>
                                @endif

                                @if ($this->canApplyDiscount())
                                    <div class="sz-pos-pair">
                                        <div>
                                            <label for="pos-discount-type" class="mb-2 block text-sm font-medium text-gray-950 dark:text-white">
                                                {{ __('pos.fields.discount_type') }}
                                            </label>
                                            <x-filament::input.wrapper>
                                                <x-filament::input.select id="pos-discount-type" wire:model.live="discountType">
                                                    <option value="amount">{{ __('pos.discount_types.amount') }}</option>
                                                    <option value="percent">{{ __('pos.discount_types.percent') }}</option>
                                                </x-filament::input.select>
                                            </x-filament::input.wrapper>
                                        </div>
                                        <div>
                                            <label for="pos-discount-value" class="mb-2 block text-sm font-medium text-gray-950 dark:text-white">
                                                {{ __('invoices.fields.discount') }}
                                            </label>
                                            <x-filament::input.wrapper>
                                                <x-filament::input
                                                    id="pos-discount-value"
                                                    type="number"
                                                    min="0"
                                                    wire:model.live.debounce.400ms="discountValue"
                                                />
                                            </x-filament::input.wrapper>
                                        </div>
                                    </div>
                                @endif

                                <div>
                                    <label for="pos-note" class="mb-2 block text-sm font-medium text-gray-950 dark:text-white">
                                        {{ __('invoices.fields.note') }}
                                    </label>
                                    <x-filament::input.wrapper>
                                        <textarea
                                            id="pos-note"
                                            rows="2"
                                            wire:model="note"
                                            class="fi-input"
                                        ></textarea>
                                    </x-filament::input.wrapper>
                                </div>
                            </div>
                        </details>
                    </div>

                    {{-- Summary --}}
                    <dl class="space-y-1 border-t border-gray-100 pt-3 text-sm dark:border-white/10">
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <dt>{{ __('pos.summary.subtotal') }}</dt>
                            <dd>{{ number_format($this->subtotal) }} SYP</dd>
                        </div>
                        @if ($this->discountPreview > 0)
                            <div class="flex justify-between text-danger-600 dark:text-danger-400">
                                <dt>{{ __('invoices.fields.discount') }}</dt>
                                <dd>- {{ number_format($this->discountPreview) }} SYP</dd>
                            </div>
                        @endif
                        <div class="flex justify-between text-base font-bold text-[#023D55] dark:text-[#F99E1E]">
                            <dt>{{ __('invoices.fields.total') }}</dt>
                            <dd>{{ number_format(max(0, $this->subtotal - $this->discountPreview)) }} SYP</dd>
                        </div>
                    </dl>

                    {{-- Buttons --}}
                    <div class="grid gap-2">
                        <x-filament::button
                            color="secondary"
                            icon="heroicon-o-banknotes"
                            wire:click="collect"
                            wire:loading.attr="disabled"
                            class="dark:ring-1 dark:ring-[#939598]"
                        >
                            {{ __('pos.actions.collect_and_issue') }}
                        </x-filament::button>

                        <x-filament::button
                            color="primary"
                            icon="heroicon-o-clock"
                            wire:click="saveUnpaid"
                            wire:loading.attr="disabled"
                        >
                            {{ __('pos.actions.save_unpaid') }}
                        </x-filament::button>

                        <x-filament::button
                            color="gray"
                            icon="heroicon-o-trash"
                            wire:click="clearCart"
                            wire:loading.attr="disabled"
                        >
                            {{ __('pos.actions.clear') }}
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        </div>
    </div>

    </div>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('pos-open-print', ({ url }) => {
                window.open(url, '_blank', 'noopener');
            });
        });
    </script>
</x-filament-panels::page>
