<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('finance.print.title') }} #{{ $shift->id }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Somar Sans', 'Segoe UI', Tahoma, Arial, sans-serif;
            width: 80mm;
            margin: 0 auto;
            padding: 4mm;
            color: #000;
            background: #fff;
            font-size: 12px;
            direction: rtl;
        }

        .header {
            text-align: center;
            margin-bottom: 8px;
        }

        .header img {
            max-width: 40mm;
            max-height: 20mm;
            object-fit: contain;
        }

        .report-title {
            font-size: 14px;
            font-weight: 700;
            margin-top: 4px;
        }

        .center-name {
            font-size: 13px;
            font-weight: 700;
            margin-top: 2px;
        }

        .meta {
            margin: 8px 0;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 6px 0;
        }

        .meta div {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
        }

        .totals {
            padding-top: 2px;
        }

        .totals div {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }

        .totals .grand {
            font-size: 13px;
            font-weight: 700;
            border-top: 1px solid #000;
            padding-top: 4px;
            margin-top: 4px;
        }

        .difference-off {
            font-weight: 700;
            color: #c00;
        }

        .note {
            margin-top: 8px;
            border-top: 1px dashed #000;
            padding-top: 6px;
        }

        .footer {
            margin-top: 10px;
            text-align: center;
            font-size: 11px;
            border-top: 1px dashed #000;
            padding-top: 6px;
        }

        @media print {
            body {
                width: 80mm;
                padding: 0;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <img src="{{ asset('sz.png') }}" alt="">
        <div class="report-title">{{ __('finance.print.title') }}</div>
        <div class="center-name">{{ $shift->center?->name }}</div>
    </div>

    <div class="meta">
        <div>
            <span>{{ __('finance.print.center') }}</span>
            <span>{{ $shift->center?->name }}</span>
        </div>
        <div>
            <span>{{ __('finance.print.period') }}</span>
            <span>{{ $shift->opened_at->format('Y-m-d H:i') }} &larr; {{ $shift->closed_at?->format('Y-m-d H:i') }}</span>
        </div>
    </div>

    <div class="totals">
        <div>
            <span>{{ __('finance.labels.opening_cash') }}</span>
            <span>{{ number_format($shift->opening_cash) }} SYP</span>
        </div>
        <div>
            <span>{{ __('finance.labels.cash_income') }}</span>
            <span>{{ number_format($shift->cash_income) }} SYP</span>
        </div>
        <div>
            <span>{{ __('finance.labels.expenses_total') }}</span>
            <span>- {{ number_format($shift->expenses_total) }} SYP</span>
        </div>
        <div>
            <span>{{ __('finance.labels.invoices_count') }}</span>
            <span>{{ $invoicesCount }}</span>
        </div>
        <div class="grand">
            <span>{{ __('finance.labels.expected_cash') }}</span>
            <span>{{ number_format($shift->opening_cash + $shift->cash_income - $shift->expenses_total) }} SYP</span>
        </div>
        <div>
            <span>{{ __('finance.labels.counted_cash') }}</span>
            <span>{{ number_format($shift->counted_cash) }} SYP</span>
        </div>
        <div @class(['difference-off' => $shift->difference !== 0])>
            <span>{{ __('finance.labels.difference') }}</span>
            <span>{{ number_format($shift->difference) }} SYP</span>
        </div>
    </div>

    @if ($shift->closing_note)
        <div class="note">
            <div>{{ __('finance.labels.closing_note') }}:</div>
            <div>{{ $shift->closing_note }}</div>
        </div>
    @endif

    <div class="footer">
        <div>{{ __('finance.print.user') }}: {{ $shift->user?->name }}</div>
        <div>{{ __('finance.print.printed_at') }}: {{ now()->format('Y-m-d H:i') }}</div>
    </div>
</body>
</html>
