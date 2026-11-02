<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('invoices.labels.invoice') }} #{{ $invoice->number }}</title>
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

        .center-name {
            font-size: 14px;
            font-weight: 700;
            margin-top: 4px;
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

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        th, td {
            padding: 3px 2px;
            text-align: right;
            vertical-align: top;
        }

        th {
            border-bottom: 1px solid #000;
            font-size: 11px;
        }

        td.num, th.num {
            text-align: left;
            white-space: nowrap;
        }

        .totals {
            border-top: 1px dashed #000;
            padding-top: 6px;
        }

        .totals div {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
        }

        .totals .grand {
            font-size: 14px;
            font-weight: 700;
            border-top: 1px solid #000;
            padding-top: 4px;
            margin-top: 4px;
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
        <div class="center-name">{{ $invoice->center?->name }}</div>
    </div>

    <div class="meta">
        <div>
            <span>{{ __('invoices.fields.number') }}</span>
            <span>#{{ $invoice->number }}</span>
        </div>
        <div>
            <span>{{ __('invoices.fields.created_at') }}</span>
            <span>{{ $invoice->created_at->format('Y-m-d H:i') }}</span>
        </div>
        <div>
            <span>{{ __('invoices.fields.beneficiary_name') }}</span>
            <span>{{ $invoice->beneficiary_name }}</span>
        </div>
        @if ($invoice->student_number)
            <div>
                <span>{{ __('invoices.fields.student_number') }}</span>
                <span>{{ $invoice->student_number }}</span>
            </div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('invoices.fields.item') }}</th>
                <th class="num">{{ __('invoices.fields.quantity') }}</th>
                <th class="num">{{ __('invoices.fields.unit_price') }}</th>
                <th class="num">{{ __('invoices.fields.line_total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $line)
                <tr>
                    <td>{{ $line->inventoryItem?->name ?? $line->service?->name }}</td>
                    <td class="num">{{ $line->quantity }}</td>
                    <td class="num">{{ number_format($line->unit_price) }}</td>
                    <td class="num">{{ number_format($line->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        @if ($invoice->discount > 0)
            <div>
                <span>{{ __('invoices.fields.discount') }}</span>
                <span>- {{ number_format($invoice->discount) }} SYP</span>
            </div>
        @endif
        <div class="grand">
            <span>{{ __('invoices.fields.total') }}</span>
            <span>{{ number_format($invoice->total) }} SYP</span>
        </div>
    </div>

    <div class="footer">
        <div>{{ __('invoices.fields.user') }}: {{ $invoice->user?->name }}</div>
        @if ($invoice->note)
            <div>{{ $invoice->note }}</div>
        @endif
    </div>
</body>
</html>
