<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Catalog --}}
        <div class="lg:col-span-2 space-y-4">
            @if ($this->isGeneralAdmin())
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <label for="pos-center" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ __('pos.fields.center') }}
                    </label>
                    <select
                        id="pos-center"
                        wire:model.live="centerId"
                        class="block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    >
                        <option value="">{{ __('pos.messages.select_center') }}</option>
                        @foreach ($this->centers as $center)
                            <option value="{{ $center->id }}">{{ $center->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($this->centerId)
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
                    <div class="flex items-center gap-2 rounded-xl bg-success-50 px-3 py-2 text-xs font-medium text-success-700 ring-1 ring-success-200 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-500/30">
                        <x-heroicon-o-check-circle class="h-4 w-4 shrink-0" />
                        <span>
                            {{ __('finance.shift_status.open') }}
                            — {{ __('finance.labels.opened_at') }}: {{ $this->openShift->opened_at->format('Y-m-d H:i') }}
                        </span>
                    </div>
                @endif

                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 space-y-4">
                    {{-- Search --}}
                    <input
                        type="search"
                        wire:model.live.debounce.400ms="search"
                        placeholder="{{ __('pos.catalog.search_placeholder') }}"
                        class="block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    />

                    {{-- Category tabs --}}
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            wire:click="$set('activeTab', 'all')"
                            @class([
                                'rounded-full px-3 py-1.5 text-sm font-medium transition',
                                'bg-primary-600 text-white' => $this->activeTab === 'all',
                                'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' => $this->activeTab !== 'all',
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
                                    'bg-primary-600 text-white' => $this->activeTab === $tab,
                                    'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' => $this->activeTab !== $tab,
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
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                            @foreach ($this->catalog as $card)
                                <button
                                    type="button"
                                    wire:click="addToCart(@js($card['key']))"
                                    wire:key="card-{{ $card['key'] }}"
                                    class="flex flex-col items-start gap-1 rounded-xl border border-gray-200 p-3 text-start transition hover:border-primary-400 hover:shadow dark:border-gray-700 dark:hover:border-primary-500"
                                >
                                    <span class="w-full truncate text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $card['name'] }}
                                    </span>

                                    <span class="text-sm font-bold text-primary-600 dark:text-primary-400">
                                        {{ number_format($card['unit_price']) }} SYP
                                    </span>

                                    @if ($card['kind'] === 'inventory')
                                        <span @class([
                                            'rounded-full px-2 py-0.5 text-xs font-medium',
                                            'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' => $card['stock'] <= 0,
                                            'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-400' => $card['stock'] > 0 && $card['stock'] <= $card['threshold'],
                                            'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-400' => $card['stock'] > $card['threshold'],
                                        ])>
                                            {{ __('pos.catalog.stock') }}: {{ $card['stock'] }}
                                        </span>
                                    @else
                                        <span class="rounded-full bg-info-100 px-2 py-0.5 text-xs font-medium text-info-700 dark:bg-info-500/20 dark:text-info-400">
                                            {{ __('invoices.kinds.service') }}
                                        </span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                <div class="rounded-xl bg-white p-8 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
                    {{ __('pos.messages.select_center') }}
                </div>
            @endif
        </div>

        {{-- Invoice draft --}}
        <div class="space-y-4">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 space-y-4">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">
                    {{ __('pos.cart.title') }}
                </h2>

                @if (empty($this->cart))
                    <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ __('pos.cart.empty') }}
                    </p>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($this->cart as $key => $line)
                            <li class="flex items-center gap-2 py-2" wire:key="line-{{ $key }}">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $line['name'] }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ number_format($line['unit_price']) }} SYP
                                    </p>
                                </div>

                                <input
                                    type="number"
                                    min="1"
                                    wire:model.lazy="cart.{{ $key }}.quantity"
                                    wire:change="normalizeQuantity(@js($key))"
                                    class="w-16 rounded-lg border-gray-300 text-center text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                    aria-label="{{ __('invoices.fields.quantity') }}"
                                />

                                <span class="w-20 text-end text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ number_format(max(1, (int) $line['quantity']) * (int) $line['unit_price']) }}
                                </span>

                                <button
                                    type="button"
                                    wire:click="removeLine(@js($key))"
                                    class="text-danger-600 hover:text-danger-700 dark:text-danger-400"
                                    aria-label="{{ __('pos.cart.remove') }}"
                                >
                                    <x-heroicon-o-trash class="h-5 w-5" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- Beneficiary --}}
                <div class="space-y-3">
                    {{-- Student search: links the invoice to a registered student --}}
                    <div class="relative">
                        <label for="pos-student-search" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ __('pos.fields.student_search') }}
                        </label>

                        @if ($studentId)
                            <div class="flex items-center justify-between gap-2 rounded-lg border border-primary-300 bg-primary-50 px-3 py-2 text-sm dark:border-primary-700 dark:bg-primary-950">
                                <span class="font-medium text-primary-700 dark:text-primary-300">
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
                        @else
                            <input
                                id="pos-student-search"
                                type="text"
                                wire:model.live.debounce.400ms="studentSearch"
                                placeholder="{{ __('pos.fields.student_search_placeholder') }}"
                                class="block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                            />

                            @if ($this->studentResults->isNotEmpty())
                                <ul class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                                    @foreach ($this->studentResults as $result)
                                        <li>
                                            <button
                                                type="button"
                                                wire:click="selectStudent('{{ $result['id'] }}')"
                                                class="flex w-full items-center justify-between px-3 py-2 text-start text-sm hover:bg-gray-50 dark:hover:bg-gray-700"
                                            >
                                                <span class="text-gray-800 dark:text-gray-100">{{ $result['name'] }}</span>
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
                        @endif
                    </div>

                    <div>
                        <label for="pos-beneficiary" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ __('invoices.fields.beneficiary_name') }} <span class="text-danger-500">*</span>
                        </label>
                        <input
                            id="pos-beneficiary"
                            type="text"
                            wire:model="beneficiaryName"
                            @disabled($studentId)
                            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm disabled:bg-gray-100 disabled:text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:disabled:bg-gray-900"
                        />
                    </div>

                    <div>
                        <label for="pos-student-number" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ __('invoices.fields.student_number') }}
                        </label>
                        <input
                            id="pos-student-number"
                            type="text"
                            wire:model="studentNumber"
                            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                        />
                    </div>

                    @if ($this->canApplyDiscount())
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label for="pos-discount-type" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ __('pos.fields.discount_type') }}
                                </label>
                                <select
                                    id="pos-discount-type"
                                    wire:model.live="discountType"
                                    class="block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                >
                                    <option value="amount">{{ __('pos.discount_types.amount') }}</option>
                                    <option value="percent">{{ __('pos.discount_types.percent') }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="pos-discount-value" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ __('invoices.fields.discount') }}
                                </label>
                                <input
                                    id="pos-discount-value"
                                    type="number"
                                    min="0"
                                    wire:model.live.debounce.400ms="discountValue"
                                    class="block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                />
                            </div>
                        </div>
                    @endif

                    <div>
                        <label for="pos-note" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ __('invoices.fields.note') }}
                        </label>
                        <textarea
                            id="pos-note"
                            rows="2"
                            wire:model="note"
                            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                        ></textarea>
                    </div>
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
                    <div class="flex justify-between text-base font-bold text-gray-900 dark:text-gray-100">
                        <dt>{{ __('invoices.fields.total') }}</dt>
                        <dd>{{ number_format(max(0, $this->subtotal - $this->discountPreview)) }} SYP</dd>
                    </div>
                </dl>

                {{-- Buttons --}}
                <div class="grid gap-2">
                    <x-filament::button
                        color="success"
                        icon="heroicon-o-banknotes"
                        wire:click="collect"
                        wire:loading.attr="disabled"
                    >
                        {{ __('pos.actions.collect_and_issue') }}
                    </x-filament::button>

                    <x-filament::button
                        color="warning"
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
