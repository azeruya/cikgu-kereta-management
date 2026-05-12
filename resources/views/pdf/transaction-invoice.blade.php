@php use Illuminate\Support\Str; @endphp

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Invoice</title>

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

    /* ── Invoice title block ─── */
    .invoice-label {
        font-size: 8.5px;
        font-weight: bold;
        letter-spacing: 2.5px;
        text-transform: uppercase;
        color: #1d6fad;
        margin-bottom: 2px;
    }

    .invoice-number {
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

    /* ── Status badge (top right) ─── */
    .status-badge-unpaid {
        display: inline;
        background: #fff7ed;
        color: #c2410c;
        font-size: 8.5px;
        font-weight: bold;
        letter-spacing: 1px;
        text-transform: uppercase;
        padding: 4px 10px;
        border-radius: 4px;
        border: 1px solid #fed7aa;
    }

    .status-badge-paid {
        display: inline;
        background: #f0fdf4;
        color: #166534;
        font-size: 8.5px;
        font-weight: bold;
        letter-spacing: 1px;
        text-transform: uppercase;
        padding: 4px 10px;
        border-radius: 4px;
        border: 1px solid #dcfce7;
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
        color: #1d6fad;
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
    .items-table-wrap {
        border: 1px solid #e8e8e8;
        border-radius: 8px;
        overflow: hidden;
    }

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

    /* ── Summary ─── */
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
        color: #1d6fad;
        text-align: right;
        padding: 3px 0;
    }

    /* ── Payment instructions box ─── */
    .instructions-box {
        border: 1px solid #e8e8e8;
        border-radius: 8px;
        padding: 12px 14px;
    }

    .instructions-text {
        font-size: 10px;
        color: #666;
        line-height: 1.8;
    }

    .instructions-text strong {
        color: #111;
    }

    /* ── Due date highlight ─── */
    .due-box {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 6px;
        padding: 10px 14px;
        margin-bottom: 12px;
    }

    .due-label {
        font-size: 8px;
        font-weight: bold;
        letter-spacing: 1.4px;
        text-transform: uppercase;
        color: #1d6fad;
        margin-bottom: 3px;
    }

    .due-date {
        font-size: 13px;
        font-weight: bold;
        color: #1e3a5f;
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
    $isPaid     = $transaction->payments->sum('amount_paid') >= $total;
@endphp

<body>

{{-- ══════════════════════════════════════════
     HEADER
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
                            Phone &nbsp;/&nbsp; Email
                        </div>
                    </td>
                </tr>
            </table>
        </td>

        {{-- Right: Invoice info --}}
        <td style="width: 45%; text-align: right; vertical-align: middle;">
            <div class="invoice-label">Invoice</div>
            <div class="invoice-number">INV-{{ str_pad($transaction->id, 6, '0', STR_PAD_LEFT) }}</div>
            <div class="meta-line">Doc No. &nbsp;<strong>{{ $transaction->document_number ?? '—' }}</strong></div>
            <div class="meta-line">Date &nbsp;<strong>{{ optional($transaction->created_at)->format('d M Y') ?? '—' }}</strong></div>
            <div style="margin-top: 8px;">
                @if($isPaid)
                    <span class="status-badge-paid">Paid</span>
                @else
                    <span class="status-badge-unpaid">Unpaid</span>
                @endif
            </div>
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
                <div class="eyebrow">Billed To</div>
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
     PAYMENT INSTRUCTIONS  +  SUMMARY
══════════════════════════════════════════ --}}
<table style="margin-top: 20px;">
    <tr>
        {{-- Left: Payment Instructions --}}
        <td style="width: 58%; padding-right: 16px; vertical-align: top;">
            <div class="section-label" style="margin-top: 0;">Payment Instructions</div>

            <div class="due-box">
                <div class="due-label">Amount Due</div>
                <div class="due-date">RM {{ number_format($total, 2) }}</div>
            </div>

            <div class="instructions-box">
                <div class="instructions-text">
                    Please make payment upon receiving this invoice.<br><br>
                    <strong>Bank Transfer</strong><br>
                    Bank &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: Maybank<br>
                    Account &nbsp;: 1234 5678 9012<br>
                    Name &nbsp;&nbsp;&nbsp;&nbsp;: Vulcan Auto Service Sdn Bhd<br><br>
                    Please use <strong>INV-{{ str_pad($transaction->id, 6, '0', STR_PAD_LEFT) }}</strong> as your payment reference.
                </div>
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
                            <td class="sum-total-label">Total Due</td>
                            <td class="sum-total-amount">RM {{ number_format($total, 2) }}</td>
                        </tr>
                    </table>
                </div>
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
                Please proceed with payment based on this invoice.<br>
                For enquiries, kindly contact <strong>Vulcan Auto Service.</strong>
            </div>
        </td>
        <td style="width: 40%; vertical-align: middle;">
            <div class="doc-ref">
                {{ $transaction->document_number ?? '' }}<br>
                INV-{{ str_pad($transaction->id, 6, '0', STR_PAD_LEFT) }}
            </div>
        </td>
    </tr>
</table>

</body>
</html>