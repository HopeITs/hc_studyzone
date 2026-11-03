<div class="space-y-3">
    @if ($invoices->isEmpty())
        <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
            {{ __('pos.unpaid.empty') }}
        </p>
    @else
        <ul class="divide-y divide-gray-100 dark:divide-white/10">
            @foreach ($invoices as $invoice)
                <li class="flex flex-wrap items-center gap-2 py-3" wire:key="unpaid-{{ $invoice->id }}">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            #{{ $invoice->number }} — {{ $invoice->beneficiary_name }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ number_format($invoice->total) }} SYP
                            · {{ $invoice->created_at->format('Y-m-d H:i') }}
                        </p>
                    </div>

                    <x-filament::button
                        size="sm"
                        color="secondary"
                        icon="heroicon-o-banknotes"
                        wire:click="collectInvoice({{ $invoice->id }})"
                        wire:loading.attr="disabled"
                    >
                        {{ __('invoices.actions.collect') }}
                    </x-filament::button>

                    @if ($canCancel)
                        <x-filament::button
                            size="sm"
                            color="danger"
                            icon="heroicon-o-x-circle"
                            wire:click="cancelInvoice({{ $invoice->id }})"
                            wire:loading.attr="disabled"
                        >
                            {{ __('invoices.actions.cancel') }}
                        </x-filament::button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
