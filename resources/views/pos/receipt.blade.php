<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $order->number }}</title>
    <style>
        /* Sized for 80 mm thermal printers (≈72 mm printable). */
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e2e8f0; font: 12px/1.35 "Courier New", ui-monospace, monospace; color: #000; }
        .paper { width: 76mm; margin: 12px auto; background: #fff; padding: 4mm 3mm 6mm; box-shadow: 0 4px 20px rgba(0,0,0,.15); }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: 700; }
        .big { font-size: 16px; }
        .muted { color: #333; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 1px 0; }
        .item-name { word-break: break-word; }
        .barcode svg { width: 100%; height: 34px; }
        .actions { width: 76mm; margin: 0 auto 16px; display: flex; gap: 8px; }
        .actions button, .actions a { flex: 1; padding: 10px; border: 0; border-radius: 8px; font: 600 13px system-ui, sans-serif; cursor: pointer; text-align: center; text-decoration: none; }
        .print { background: #e51d1d; color: #fff; }
        .close { background: #fff; color: #0f172a; }
        @media print {
            body { background: #fff; }
            .paper { margin: 0; box-shadow: none; width: 100%; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
<div class="paper">
    <div class="center">
        <div class="bold big">{{ setting('store_name') }}</div>
        <div>{{ setting('store_address') }}</div>
        <div>Tel: {{ setting('store_phone') }}</div>
        @if (setting('bin_number'))<div>BIN: {{ setting('bin_number') }}</div>@endif
    </div>
    <hr>
    <table>
        <tr><td>Receipt</td><td class="right bold">{{ $order->number }}</td></tr>
        <tr><td>Date</td><td class="right">{{ $order->created_at->format('d/m/Y h:i A') }}</td></tr>
        @if ($order->servedBy)<tr><td>Cashier</td><td class="right">{{ $order->servedBy->name }}</td></tr>@endif
        @if ($order->customer_name || $order->user)<tr><td>Customer</td><td class="right">{{ $order->customer_label }}</td></tr>@endif
        @if ($order->phone)<tr><td>Phone</td><td class="right">{{ $order->phone }}</td></tr>@endif
    </table>
    <hr>
    <table>
        @foreach ($order->items as $item)
            <tr><td colspan="2" class="item-name">{{ $item->product?->name ?? 'Item' }}</td></tr>
            <tr>
                <td class="muted">&nbsp;&nbsp;{{ $item->quantity }} x {{ number_format($item->price, 2) }}</td>
                <td class="right">{{ number_format($item->line_total, 2) }}</td>
            </tr>
        @endforeach
    </table>
    <hr>
    <table>
        <tr><td>Items</td><td class="right">{{ $order->items->sum('quantity') }}</td></tr>
        <tr><td>Subtotal</td><td class="right">{{ number_format($order->items_subtotal, 2) }}</td></tr>
        @if ($order->discount > 0)
            <tr><td>Discount{{ $order->coupon ? ' ' . $order->coupon->name : '' }}</td><td class="right">-{{ number_format($order->discount, 2) }}</td></tr>
        @endif
        @if ((float) $order->delivery_fee > 0)
            <tr><td>Delivery</td><td class="right">{{ number_format($order->delivery_fee, 2) }}</td></tr>
        @endif
        <tr class="bold big"><td>TOTAL (Tk)</td><td class="right">{{ number_format($order->total_amount, 2) }}</td></tr>
        @if ($vatRate > 0)
            <tr><td class="muted">Incl. VAT {{ rtrim(rtrim(number_format($vatRate, 2), '0'), '.') }}%</td><td class="right muted">{{ number_format($vatIncluded, 2) }}</td></tr>
        @endif
    </table>
    <hr>
    <table>
        <tr><td>Paid by</td><td class="right">{{ $order->payment_method_label }}</td></tr>
        @if ($order->payment_method === 'cash' && $order->amount_tendered !== null)
            <tr><td>Cash</td><td class="right">{{ number_format($order->amount_tendered, 2) }}</td></tr>
            <tr class="bold"><td>Change</td><td class="right">{{ number_format($order->change_due, 2) }}</td></tr>
        @endif
        @if ($order->status === 'returned')
            <tr class="bold"><td colspan="2" class="center">*** RETURNED · {{ strtoupper($order->payment_status) }} ***</td></tr>
        @elseif ($order->status === 'cancelled')
            <tr class="bold"><td colspan="2" class="center">*** CANCELLED ***</td></tr>
        @endif
    </table>
    <hr>
    <div class="barcode center">{!! $orderBarcode !!}</div>
    <div class="center">{{ $order->number }}</div>
    @if (setting('receipt_footer'))
        <p class="center">{{ setting('receipt_footer') }}</p>
    @endif
</div>

<div class="actions">
    <button class="print" onclick="window.print()">Print receipt</button>
    <a class="close" href="#" onclick="window.opener ? window.close() : history.back(); return false;">Close</a>
</div>

@if ($autoprint)
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
@endif
</body>
</html>
