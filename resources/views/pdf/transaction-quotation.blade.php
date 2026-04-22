<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quotation</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #333;
        }

        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .title {
            font-size: 22px;
            font-weight: bold;
        }

        .section {
            margin-bottom: 16px;
        }

        .box {
            border: 1px solid #eee;
            padding: 10px;
            border-radius: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            border-bottom: 1px solid #ddd;
            padding: 8px;
        }

        td {
            padding: 8px;
            border-bottom: 1px solid #f2f2f2;
        }

        .right {
            text-align: right;
        }

        .total-box {
            margin-top: 10px;
            width: 40%;
            float: right;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
        }

        .grand-total {
            font-weight: bold;
            font-size: 14px;
        }

        .footer {
            margin-top: 40px;
            font-size: 11px;
            color: #666;
        }
    </style>
</head>
<body>

<div class="header">
    <div>
        <div class="title">QUOTATION</div>
        <div>No: {{ 'QUO-' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT) }}</div>
        <div>Date: {{ optional($transaction->created_at)->format('d M Y') }}</div>
    </div>

    <div>
        <strong>Your Workshop Name</strong><br>
        Address line<br>
        Phone / Email
    </div>
</div>

<div class="section box">
    <strong>Customer</strong><br>
    {{ $transaction->customer->name ?? '-' }}<br>
    {{ $transaction->customer->phone ?? '-' }}
</div>

<div class="section box">
    <strong>Vehicle</strong><br>
    {{ $transaction->vehicle->license_plate ?? '-' }}<br>
    {{ $transaction->vehicle->make ?? '' }} {{ $transaction->vehicle->model ?? '' }}
</div>

<div class="section">
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th class="right">Unit</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transaction->items as $item)
                <tr>
                    <td>
                        {{ $item->part->name ?? $item->service_name ?? 'Item' }}
                    </td>
                    <td>{{ $item->quantity ?? 1 }}</td>
                    <td class="right">RM {{ number_format($item->selling_price, 2) }}</td>
                    <td class="right">RM {{ number_format($item->total_price, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-box">
        <div class="total-row">
            <span>Subtotal</span>
            <span>RM {{ number_format($transaction->total_amount, 2) }}</span>
        </div>

        <div class="total-row">
            <span>Discount</span>
            <span>RM {{ number_format($transaction->discount_amount ?? 0, 2) }}</span>
        </div>

        <div class="total-row grand-total">
            <span>Total</span>
            <span>
                RM {{ number_format($transaction->total_amount - ($transaction->discount_amount ?? 0), 2) }}
            </span>
        </div>
    </div>
</div>

<div class="footer">
    This is an estimated quotation. Final price may change after inspection.
</div>

</body>
</html>