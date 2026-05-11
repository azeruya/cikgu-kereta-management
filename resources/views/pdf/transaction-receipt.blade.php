<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #222;
            line-height: 1.45;
        }

        .header {
            width: 100%;
            margin-bottom: 22px;
        }

        .header td {
            border: none;
            padding: 0;
            vertical-align: top;
        }

        .title {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .muted {
            color: #777;
            font-size: 11px;
        }

        .workshop {
            text-align: right;
        }

        .section {
            margin-bottom: 14px;
        }

        .two-col {
            width: 100%;
            margin-bottom: 14px;
        }

        .two-col td {
            width: 50%;
            vertical-align: top;
            border: none;
            padding: 0;
        }

        .box {
            border: 1px solid #e5e5e5;
            padding: 10px;
            border-radius: 6px;
        }

        .box-title {
            font-weight: bold;
            margin-bottom: 6px;
        }

        table.items,
        table.payments,
        table.totals {
            width: 100%;
            border-collapse: collapse;
        }

        table.items th,
        table.payments th {
            text-align: left;
            border-bottom: 1px solid #ddd;
            padding: 8px;
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
        }

        table.items td,
        table.payments td {
            padding: 8px;
            border-bottom: 1px solid #f0f0f0;
        }

        .right {
            text-align: right;
        }

        .summary-wrap {
            width: 42%;
            margin-left: auto;
            margin-top: 14px;
        }

        table.totals td {
            border: none;
            padding: 5px 0;
        }

        .grand-total td {
            border-top: 1px solid #ddd !important;
            padding-top: 8px !important;
            font-weight: bold;
            font-size: 14px;
        }

        .paid-row td {
            color: #0f7a3a;
            font-weight: bold;
        }

        .balance-row td {
            color: #b42318;
            font-weight: bold;
        }

        .footer {
            margin-top: 36px;
            padding-top: 12px;
            border-top: 1px solid #eee;
            font-size: 11px;
            color: #666;
        }
    </style>
</head>

@php
    $subtotal = (float) $transaction->total_amount;
    $discount = (float) ($transaction->discount_amount ?? 0);
    $total = $subtotal - $discount;
    $totalPaid = $transaction->payments->sum('amount_paid');
    $balanceDue = max($total - $totalPaid, 0);
@endphp

<body>

<table class="header">
    <tr>
        <td>
            <div class="title">RECEIPT</div>
            <div class="muted">Receipt No: {{ 'REC-' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT) }}</div>
            <div class="muted">Document No: {{ $transaction->document_number ?? '-' }}</div>
            <div class="muted">Date: {{ optional($transaction->paid_at)->format('d M Y') ?? now()->format('d M Y') }}</div>
        </td>

        <td class="workshop">
            <strong>Cikgu Kereta</strong><br>
            Workshop Management<br>
            Address line<br>
            Phone / Email
        </td>
    </tr>
</table>

<table class="two-col">
    <tr>
        <td style="padding-right: 8px;">
            <div class="box">
                <div class="box-title">Customer</div>
                {{ $transaction->customer->name ?? '-' }}<br>
                {{ $transaction->customer->phone ?? '-' }}<br>
                {{ $transaction->customer->email ?? '' }}
            </div>
        </td>

        <td style="padding-left: 8px;">
            <div class="box">
                <div class="box-title">Vehicle</div>
                {{ $transaction->vehicle->license_plate ?? '-' }}<br>
                {{ $transaction->vehicle->make ?? '' }} {{ $transaction->vehicle->model ?? '' }}<br>
                {{ $transaction->vehicle->year ?? '' }}
            </div>
        </td>
    </tr>
</table>

<div class="section">
    <div class="box-title">Items</div>

    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th>Type</th>
                <th class="right">Qty / Hours</th>
                <th class="right">Unit / Rate</th>
                <th class="right">Total</th>
            </tr>
        </thead>

        <tbody>
            @foreach($transaction->items as $item)
                <tr>
                    <td>
                        {{ $item->part->name ?? $item->service_name ?? 'Item' }}
                        @if($item->part?->variant)
                            <br><span class="muted">{{ $item->part->variant }}</span>
                        @endif
                    </td>
                    <td>{{ ucfirst($item->item_type ?? '-') }}</td>
                    <td class="right">{{ $item->quantity ?? 1 }}</td>
                    <td class="right">RM {{ number_format((float) $item->selling_price, 2) }}</td>
                    <td class="right">RM {{ number_format((float) $item->total_price, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="section">
    <div class="box-title">Payment History</div>

    @if($transaction->payments && $transaction->payments->count())
        <table class="payments">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th class="right">Amount</th>
                </tr>
            </thead>

            <tbody>
                @foreach($transaction->payments as $payment)
                    <tr>
                        <td>{{ optional($payment->payment_date)->format('d M Y H:i') ?? '-' }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $payment->payment_method ?? '-')) }}</td>
                        <td>{{ $payment->payment_reference ?? '-' }}</td>
                        <td class="right">RM {{ number_format((float) $payment->amount_paid, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="box muted">No payment records found.</div>
    @endif
</div>

<div class="summary-wrap">
    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="right">RM {{ number_format($subtotal, 2) }}</td>
        </tr>

        <tr>
            <td>Discount</td>
            <td class="right">RM {{ number_format($discount, 2) }}</td>
        </tr>

        <tr class="grand-total">
            <td>Total</td>
            <td class="right">RM {{ number_format($total, 2) }}</td>
        </tr>

        <tr class="paid-row">
            <td>Total Paid</td>
            <td class="right">RM {{ number_format($totalPaid, 2) }}</td>
        </tr>

        <tr class="{{ $balanceDue > 0 ? 'balance-row' : '' }}">
            <td>Balance Due</td>
            <td class="right">RM {{ number_format($balanceDue, 2) }}</td>
        </tr>
    </table>
</div>

<div style="clear: both;"></div>

<div class="footer">
    @if($balanceDue > 0)
        Partial payment received. Remaining balance: RM {{ number_format($balanceDue, 2) }}.
    @else
        Payment received in full. Thank you for your business.
    @endif
</div>

</body>
</html>