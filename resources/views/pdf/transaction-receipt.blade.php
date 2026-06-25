@php use Illuminate\Support\Str; @endphp

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Receipt</title>

<style>
    @page {
        margin: 36px 44px;
    }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 11px;
        color: #1a1a1a;
        margin: 0;
        padding: 0;
        background: #fff;
        line-height: 1.5;
    }

    /* ── Reset ─── */
    table {
        width: 100%;
        border-collapse: collapse;
    }
    td { vertical-align: top; }

    /* ── Utilities ─── */
    .right  { text-align: right; }
    .center { text-align: center; }
    .muted  { color: #999; font-size: 9.5px; }

    /* ── Logo ─── */
    .logo {
        width: 110px;
        height: 110px;
        border-radius: 6px;
    }

    /* ── Brand ─── */
    .brand-name {
        font-size: 15px;
        font-weight: bold;
        color: #111;
        letter-spacing: -0.2px;
        line-height: 1.2;
    }

    .brand-tagline {
        font-size: 8.5px;
        color: #aaa;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        margin-top: 2px;
    }

    .brand-contact {
        font-size: 9.5px;
        color: #888;
        margin-top: 6px;
        line-height: 1.7;
    }

    /* ── Receipt title block ─── */
    .receipt-label {
        font-size: 8.5px;
        font-weight: bold;
        letter-spacing: 2.5px;
        text-transform: uppercase;
        color: #c1121f;
        margin-bottom: 2px;
    }

    .receipt-number {
        font-size: 20px;
        font-weight: bold;
        color: #111;
        line-height: 1;
        margin-bottom: 10px;
    }

    .meta-line {
        font-size: 9.5px;
        color: #999;
        margin-bottom: 2px;
    }

    .meta-line strong {
        color: #333;
        font-weight: bold;
    }

    /* ── Divider ─── */
    .divider {
        border-top: 1.5px solid #111;
        margin: 20px 0;
    }

    /* ── Info boxes ─── */
    .info-box {
        border: 1px solid #e8e8e8;
        border-radius: 8px;
        padding: 12px 14px;
    }

    .eyebrow {
        font-size: 8px;
        font-weight: bold;
        letter-spacing: 1.6px;
        text-transform: uppercase;
        color: #c1121f;
        margin-bottom: 7px;
    }

    .info-name {
        font-size: 12px;
        font-weight: bold;
        color: #111;
        margin-bottom: 3px;
    }

    .info-detail {
        font-size: 10px;
        color: #666;
        line-height: 1.7;
    }

    /* ── Section label ─── */
    .section-label {
        font-size: 8px;
        font-weight: bold;
        letter-spacing: 1.6px;
        text-transform: uppercase;
        color: #bbb;
        margin-bottom: 8px;
        margin-top: 20px;
    }

    /* ── Items table ─── */
    .items-table th {
        background: #111;
        color: #c0c0c0;
        padding: 9px 11px;
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        text-align: left;
    }

    .items-table td {
        padding: 11px 11px;
        border-bottom: 1px solid #f3f3f3;
    }

    .items-table tr:last-child td {
        border-bottom: none;
    }

    .items-table-wrap {
        border: 1px solid #e8e8e8;
        border-radius: 8px;
        overflow: hidden;
    }

    .item-name {
        font-weight: bold;
        font-size: 11px;
        color: #111;
    }

    .item-sub {
        font-size: 9.5px;
        color: #bbb;
        margin-top: 2px;
    }

    .badge {
        display: inline;
        padding: 2px 7px;
        border-radius: 3px;
        font-size: 8px;
        font-weight: bold;
        letter-spacing: 0.3px;
        text-transform: uppercase;
    }

    .badge-part    { background: #eef0ff; color: #3730a3; }
    .badge-service { background: #ecfdf3; color: #166534; }

    /* ── Payment table ─── */
    .payment-table-wrap {
        border: 1px solid #e8e8e8;
        border-radius: 8px;
        overflow: hidden;
    }

    .payment-table th {
        background: #111;
        color: #c0c0c0;
        padding: 8px 11px;
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        text-align: left;
    }

    .payment-table td {
        padding: 10px 11px;
        border-bottom: 1px solid #f3f3f3;
        font-size: 10px;
    }

    .payment-table tr:last-child td {
        border-bottom: none;
    }

    /* ── Summary box ─── */
    .summary-wrap {
        border: 1px solid #e8e8e8;
        border-radius: 8px;
        overflow: hidden;
    }

    .summary-inner {
        padding: 14px;
    }

    .sum-row td {
        padding: 4px 0;
        font-size: 10.5px;
        border-bottom: none;
    }

    .sum-label { color: #777; }
    .sum-value { text-align: right; color: #444; }

    .sum-divider {
        border-top: 1px solid #ebebeb;
        margin: 8px 0;
    }

    .sum-total-label {
        font-size: 12px;
        font-weight: bold;
        color: #111;
        padding: 3px 0;
    }

    .sum-total-amount {
        font-size: 17px;
        font-weight: bold;
        color: #c1121f;
        text-align: right;
        padding: 3px 0;
    }

    .sum-paid-label { color: #166534; font-weight: bold; font-size: 10.5px; padding: 3px 0; }
    .sum-paid-value { color: #166534; font-weight: bold; font-size: 10.5px; text-align: right; padding: 3px 0; }
    .sum-bal-label  { color: #aaa; font-size: 10.5px; padding: 3px 0; }
    .sum-bal-value  { color: #555; font-size: 10.5px; text-align: right; padding: 3px 0; }

    /* ── Status strip ─── */
    .status-paid {
        background: #f0fdf4;
        color: #166534;
        text-align: center;
        padding: 11px;
        font-size: 11px;
        font-weight: bold;
        border-top: 1px solid #dcfce7;
    }

    .status-partial {
        background: #fff7ed;
        color: #c2410c;
        text-align: center;
        padding: 11px;
        font-size: 11px;
        font-weight: bold;
        border-top: 1px solid #fed7aa;
    }

    .status-sub {
        font-size: 9px;
        font-weight: normal;
        color: inherit;
        margin-top: 2px;
    }

    /* ── Footer ─── */
    .footer-divider {
        border-top: 1.5px solid #111;
        margin-top: 24px;
        margin-bottom: 14px;
    }

    .thanks-text {
        font-size: 10.5px;
        color: #666;
        line-height: 1.8;
    }

    .thanks-text strong { color: #111; }

    .doc-ref {
        font-size: 9px;
        color: #ccc;
        text-align: right;
        line-height: 1.8;
    }
</style>
</head>

@php
    $subtotal   = (float) $transaction->total_amount;
    $discount   = (float) ($transaction->discount_amount ?? 0);
    $total      = $subtotal - $discount;
    $totalPaid  = $transaction->payments->sum('amount_paid');
    $balanceDue = max($total - $totalPaid, 0);
@endphp

<body>

{{-- ══════════════════════════════════════════
     HEADER  — real <table> for DomPDF compat
══════════════════════════════════════════ --}}
<table>
    <tr>
        {{-- Left: Logo + Brand --}}
        <td style="width: 55%; vertical-align: middle;">
            <table style="width: auto;">
                <tr>
                    <td style="width: 68px; padding-right: 12px; vertical-align: middle;">
                        <img src="{{ public_path('images/vulcan_bg.png') }}" class="logo" alt="Logo">
                    </td>
                    <td style="vertical-align: middle;">
                        <div class="brand-name">Vulcan Auto Service</div>
                        <div class="brand-tagline">Workshop Management</div>
                        <div class="brand-contact">
                            {{ Str::limit($transaction->branch->location ?? '—', 70) }}<br>
                        </div>
                    </td>
                </tr>
            </table>
        </td>

        {{-- Right: Receipt info --}}
        <td style="width: 45%; text-align: right; vertical-align: middle;">
            <div class="receipt-label">Receipt</div>
            <div class="receipt-number">REC-{{ str_pad($transaction->id, 6, '0', STR_PAD_LEFT) }}</div>
            <div class="meta-line">Doc No. &nbsp;<strong>{{ $transaction->document_number ?? '—' }}</strong></div>
            <div class="meta-line">Date &nbsp;<strong>{{ optional($transaction->paid_at)->format('d M Y') ?? '—' }}</strong></div>
            <div class="meta-line">Time &nbsp;<strong>{{ optional($transaction->paid_at)->format('H:i') ?? '—' }}</strong></div>
        </td>
    </tr>
</table>

<div class="divider"></div>

{{-- ══════════════════════════════════════════
     CUSTOMER / VEHICLE
══════════════════════════════════════════ --}}
<table>
    <tr>
        <td style="width: 50%; padding-right: 8px;">
            <div class="info-box">
                <div class="eyebrow">Customer</div>
                <div class="info-name">{{ $transaction->customer->name ?? '—' }}</div>
                <div class="info-detail">
                    {{ $transaction->customer->phone ?? '—' }}<br>
                    {{ $transaction->customer->email ?? '' }}
                </div>
            </div>
        </td>
        <td style="width: 50%; padding-left: 8px;">
            <div class="info-box">
                <div class="eyebrow">Vehicle</div>
                <div class="info-name">{{ $transaction->vehicle->license_plate ?? '—' }}</div>
                <div class="info-detail">
                    {{ $transaction->vehicle->make ?? '' }} {{ $transaction->vehicle->model ?? '' }}<br>
                    {{ $transaction->vehicle->year ?? '' }}
                </div>
            </div>
        </td>
    </tr>
</table>

{{-- ══════════════════════════════════════════
     ITEMS
══════════════════════════════════════════ --}}
<div class="section-label">Items</div>

<div class="items-table-wrap">
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 37%;">Description</th>
                <th style="width: 14%;">Type</th>
                <th class="right" style="width: 11%;">Qty</th>
                <th class="right" style="width: 16%;">Unit Price</th>
                <th class="right" style="width: 17%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transaction->items as $index => $item)
            <tr>
                <td class="muted">{{ $index + 1 }}</td>
                <td>
                    <div class="item-name">{{ $item->part->name ?? $item->service_name ?? 'Item' }}</div>
                    @if($item->part?->variant)
                        <div class="item-sub">{{ $item->part->variant }}</div>
                    @endif
                    @if($item->note)
                        <div class="item-sub">{{ $item->note }}</div>
                    @endif
                </td>
                <td>
                    <span class="badge {{ $item->item_type === 'service' ? 'badge-service' : 'badge-part' }}">
                        {{ ucfirst($item->item_type ?? '—') }}
                    </span>
                </td>
                <td class="right">{{ $item->quantity ?? 1 }}</td>
                <td class="right">RM {{ number_format((float) $item->selling_price, 2) }}</td>
                <td class="right" style="font-weight: bold;">RM {{ number_format((float) $item->total_price, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- ══════════════════════════════════════════
     PAYMENT HISTORY  +  SUMMARY
══════════════════════════════════════════ --}}
<table style="margin-top: 20px;">
    <tr>
        {{-- Left: Payment History --}}
        <td style="width: 58%; padding-right: 16px; vertical-align: top;">
            <div class="section-label" style="margin-top: 0;">Payment History</div>
            <div class="payment-table-wrap">
                <table class="payment-table">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Date</th>
                            <th style="width: 24%;">Method</th>
                            <th style="width: 26%;">Reference</th>
                            <th class="right" style="width: 20%;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transaction->payments as $payment)
                        <tr>
                            <td>
                                {{ optional($payment->payment_date)->format('d M Y') }}<br>
                                <span class="muted">{{ optional($payment->payment_date)->format('H:i') }}</span>
                            </td>
                            <td>{{ ucwords(str_replace('_', ' ', $payment->payment_method ?? '—')) }}</td>
                            <td class="muted">{{ $payment->payment_reference ?? '—' }}</td>
                            <td class="right" style="font-weight: bold;">RM {{ number_format((float) $payment->amount_paid, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </td>

        {{-- Right: Summary --}}
        <td style="width: 42%; vertical-align: top;">
            <div class="section-label" style="margin-top: 0;">Summary</div>
            <div class="summary-wrap">
                <div class="summary-inner">
                    <table>
                        <tr class="sum-row">
                            <td class="sum-label">Subtotal</td>
                            <td class="sum-value">RM {{ number_format($subtotal, 2) }}</td>
                        </tr>
                        <tr class="sum-row">
                            <td class="sum-label">Discount</td>
                            <td class="sum-value">&minus; RM {{ number_format($discount, 2) }}</td>
                        </tr>
                    </table>

                    <div class="sum-divider"></div>

                    <table>
                        <tr>
                            <td class="sum-total-label">Total</td>
                            <td class="sum-total-amount">RM {{ number_format($total, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="height: 8px;"></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="sum-paid-label">Paid</td>
                            <td class="sum-paid-value">RM {{ number_format($totalPaid, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="sum-bal-label">Balance Due</td>
                            <td class="sum-bal-value">RM {{ number_format($balanceDue, 2) }}</td>
                        </tr>
                    </table>
                </div>

                @if($balanceDue > 0)
                    <div class="status-partial">
                        Partially Paid
                        <div class="status-sub">Balance: RM {{ number_format($balanceDue, 2) }}</div>
                    </div>
                @else
                    <div class="status-paid">
                        Fully Paid
                        <div class="status-sub">Payment received in full.</div>
                    </div>
                @endif
            </div>
        </td>
    </tr>
</table>

{{-- ══════════════════════════════════════════
     FOOTER
══════════════════════════════════════════ --}}
<div class="footer-divider"></div>

<table>
    <tr>
        <td style="width: 60%; vertical-align: middle;">
            <div class="thanks-text">
                Thank you for trusting <strong>Vulcan Auto Service.</strong><br>
                We look forward to serving you again.
            </div>
        </td>
        <td style="width: 40%; vertical-align: middle;">
            <div class="doc-ref">
                {{ $transaction->document_number ?? '' }}<br>
                REC-{{ str_pad($transaction->id, 6, '0', STR_PAD_LEFT) }}
            </div>
        </td>
    </tr>
</table>

</body>
</html>