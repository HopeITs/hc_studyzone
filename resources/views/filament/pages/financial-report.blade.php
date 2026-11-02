<x-filament-panels::page>
    <style>
        .fr-print-only {
            display: none;
        }

        @media print {
            .fi-sidebar,
            .fi-sidebar-nav,
            .fi-topbar,
            .fi-header,
            header.fi-header,
            .fr-no-print {
                display: none !important;
            }

            .fr-print-only {
                display: block !important;
            }

            html,
            body,
            .fi-body,
            .fi-main,
            .fi-main-ctn {
                background: #fff !important;
                color: #000 !important;
            }

            .fi-main,
            .fi-main-ctn {
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
            }

            .fr-table-card {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
            }

            .fr-table-card table {
                font-size: 11px;
            }

            .fr-table-card th,
            .fr-table-card td {
                color: #000 !important;
                border-color: #999 !important;
            }
        }
    </style>

    {{-- Print-only header: title, period, centers, totals --}}
    <div class="fr-print-only" dir="rtl" style="color:#000; margin-bottom:12px;">
        <h1 style="font-size:20px; font-weight:700; text-align:center; margin-bottom:4px;">
            {{ __('reports.print.title') }}
        </h1>
        <div style="display:flex; justify-content:space-between; font-size:12px; border-bottom:1px solid #000; padding-bottom:6px; margin-bottom:6px;">
            <span>
                {{ __('reports.print.period') }}:
                {{ $this->data['from'] ?? '—' }} — {{ $this->data['to'] ?? '—' }}
            </span>
            <span>{{ __('reports.print.center') }}: {{ $this->filteredCentersLabel() }}</span>
            <span>{{ __('reports.print.printed_at') }}: {{ now()->format('Y-m-d H:i') }}</span>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:700; margin-bottom:8px;">
            <span>{{ __('reports.totals.income') }}: {{ $this->formatMoney($this->totals['income']) }}</span>
            <span>{{ __('reports.totals.expenses') }}: {{ $this->formatMoney($this->totals['expenses']) }}</span>
            <span>{{ __('reports.totals.net') }}: {{ $this->formatMoney($this->totals['net']) }}</span>
        </div>
        @if ($this->oldCurrencyEnabled())
            <p style="font-size:11px; margin-bottom:8px;">{{ __('reports.old_currency.note') }}</p>
        @endif
    </div>

    <div class="space-y-6">
        {{-- Filters --}}
        <div class="fr-no-print">
            {{ $this->form }}
        </div>

        {{-- Stat cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ __('reports.totals.income') }}
                </p>
                <p class="mt-1 text-2xl font-bold text-success-600 dark:text-success-400">
                    {{ $this->formatMoney($this->totals['income']) }}
                </p>
            </div>

            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ __('reports.totals.expenses') }}
                </p>
                <p class="mt-1 text-2xl font-bold text-danger-600 dark:text-danger-400">
                    {{ $this->formatMoney($this->totals['expenses']) }}
                </p>
            </div>

            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ __('reports.totals.net') }}
                </p>
                <p class="mt-1 text-2xl font-bold text-primary-600 dark:text-primary-400">
                    {{ $this->formatMoney($this->totals['net']) }}
                </p>
            </div>
        </div>

        {{-- Old-currency display note --}}
        @if ($this->oldCurrencyEnabled())
            <div class="fr-no-print flex items-center gap-2 rounded-xl bg-warning-50 px-3 py-2 text-xs font-medium text-warning-700 ring-1 ring-warning-200 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-500/30">
                <x-heroicon-o-information-circle class="h-4 w-4 shrink-0" />
                <span>{{ __('reports.old_currency.note') }}</span>
            </div>
        @endif

        {{-- Movements table --}}
        <div class="fr-table-card rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            @if ($this->rows->isEmpty())
                <p class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    {{ __('reports.table.empty') }}
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-start dark:border-white/10">
                                <th class="px-2 py-2 text-start font-semibold text-gray-700 dark:text-gray-200">{{ __('reports.table.date') }}</th>
                                <th class="px-2 py-2 text-start font-semibold text-gray-700 dark:text-gray-200">{{ __('reports.table.type') }}</th>
                                <th class="px-2 py-2 text-start font-semibold text-gray-700 dark:text-gray-200">{{ __('reports.table.reference') }}</th>
                                <th class="px-2 py-2 text-start font-semibold text-gray-700 dark:text-gray-200">{{ __('reports.table.description') }}</th>
                                <th class="px-2 py-2 text-start font-semibold text-gray-700 dark:text-gray-200">{{ __('reports.table.category') }}</th>
                                <th class="px-2 py-2 text-start font-semibold text-gray-700 dark:text-gray-200">{{ __('reports.table.center') }}</th>
                                <th class="px-2 py-2 text-end font-semibold text-gray-700 dark:text-gray-200">{{ __('reports.table.amount') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($this->rows as $row)
                                <tr wire:key="row-{{ $row->kind }}-{{ $loop->index }}-{{ $this->rows->currentPage() }}">
                                    <td class="whitespace-nowrap px-2 py-2 text-gray-700 dark:text-gray-300">
                                        {{ $row->date }}
                                    </td>
                                    <td class="px-2 py-2">
                                        @if ($row->kind === 'income')
                                            <span class="rounded-full bg-success-100 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/20 dark:text-success-400">
                                                {{ __('reports.kinds.income') }}
                                            </span>
                                        @else
                                            <span class="rounded-full bg-danger-100 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/20 dark:text-danger-400">
                                                {{ __('reports.kinds.expense') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-2 py-2 text-gray-700 dark:text-gray-300">
                                        {{ $row->reference }}
                                    </td>
                                    <td class="px-2 py-2 text-gray-700 dark:text-gray-300">
                                        {{ $row->description }}
                                    </td>
                                    <td class="whitespace-nowrap px-2 py-2 text-gray-500 dark:text-gray-400">
                                        @if ($row->category)
                                            {{ $row->kind === 'expense'
                                                ? __('finance.expense_types.'.$row->category)
                                                : $row->category }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-2 py-2 text-gray-500 dark:text-gray-400">
                                        {{ $this->centerNames[$row->center_id] ?? '—' }}
                                    </td>
                                    <td @class([
                                        'whitespace-nowrap px-2 py-2 text-end font-semibold',
                                        'text-success-600 dark:text-success-400' => $row->kind === 'income',
                                        'text-danger-600 dark:text-danger-400' => $row->kind !== 'income',
                                    ])>
                                        {{ $this->formatMoney((int) $row->amount) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="fr-no-print mt-4">
                    {{ $this->rows->links() }}
                </div>
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('financial-report-print', () => {
                window.print();
            });
        });
    </script>
</x-filament-panels::page>
