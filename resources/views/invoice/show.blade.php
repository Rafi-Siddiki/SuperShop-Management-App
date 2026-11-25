@extends('pdf.layout')

@section('title', 'Invoice')
@section('subtitle', $order->number . ' · ' . $order->created_at->format('d M Y'))

@section('styles')
    .addresses { width: 100%; margin-bottom: 18px; }
    .addresses td { width: 50%; vertical-align: top; padding: 10px 12px; background: #f8fafc; border-radius: 6px; }
    .totals { width: 45%; margin-left: 55%; margin-top: 12px; border-collapse: collapse; }
    .totals td { padding: 5px 8px; }
    .totals .grand td { border-top: 2px solid #0f172a; font-size: 13px; font-weight: bold; padding-top: 8px; }
    .status { text-transform: uppercase; }
    .thanks { margin-top: 30px; padding: 12px 14px; border-left: 4px solid #ff8938; background: #fff7ed; }
@endsection

@section('content')
    <table class="addresses" cellspacing="6">
        <tr>
            <td>
                <div class="muted small">BILLED TO</div>
                <div class="bold" style="margin-top:3px">{{ $order->customer_label }}</div>
                @if ($order->user)<div>{{ $order->user->email }}</div>@endif
                @if ($order->phone)<div>{{ $order->phone }}</div>@endif
            </td>
            <td>
                @if ($order->is_pos)
                    <div class="muted small">SOLD IN STORE</div>
                    <div style="margin-top:3px">{{ setting('store_address') }}</div>
                    @if ($order->servedBy)<div class="muted small">Served by {{ $order->servedBy->name }}</div>@endif
                @else
                    <div class="muted small">DELIVER TO</div>
                    <div style="margin-top:3px">{{ $order->shipping_address ?? 'Not provided' }}</div>
                @endif
                <div class="muted small" style="margin-top:6px">
                    Status: <span class="bold status">{{ $order->status }}</span> · Payment: {{ $order->payment_method_label }}
                </div>
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr><th style="width:5%">#</th><th>Item</th><th class="right" style="width:15%">Unit price</th><th class="center" style="width:8%">Qty</th><th class="right" style="width:17%">Amount</th></tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->product?->name ?? 'Removed product' }}<br><span class="muted small">{{ $item->product?->category }}</span></td>
                    <td class="right">{{ money($item->price, 'Tk ') }}</td>
                    <td class="center">{{ $item->quantity }}</td>
                    <td class="right">{{ money($item->line_total, 'Tk ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="muted">Subtotal</td><td class="right">{{ money($order->items_subtotal, 'Tk ') }}</td></tr>
        @if ($order->discount > 0)
            <tr><td class="muted">Discount{{ $order->coupon ? ' (' . $order->coupon->name . ')' : '' }}</td><td class="right">- {{ money($order->discount, 'Tk ') }}</td></tr>
        @endif
        @unless ($order->is_pos)
            <tr><td class="muted">Delivery</td><td class="right">{{ (float) $order->delivery_fee > 0 ? money($order->delivery_fee, 'Tk ') : 'Free' }}</td></tr>
        @endunless
        <tr class="grand"><td>Total</td><td class="right">{{ money($order->total_amount, 'Tk ') }}</td></tr>
        @if ((float) setting('vat_rate') > 0)
            <tr><td class="muted small">Includes VAT {{ rtrim(rtrim(number_format(setting('vat_rate'), 2), '0'), '.') }}%</td>
                <td class="right muted small">{{ money(round($order->total_amount * setting('vat_rate') / (100 + setting('vat_rate')), 2), 'Tk ') }}</td></tr>
        @endif
        <tr><td class="muted small">Payment</td><td class="right small">{{ $order->payment_method_label }} · {{ strtoupper($order->payment_status) }}</td></tr>
    </table>

    <div class="thanks">
        <div class="bold">Thank you for shopping with {{ setting('store_name') }}!</div>
        <div class="muted small" style="margin-top:3px">Questions about this order? Call {{ setting('store_phone') }} or email {{ setting('store_email') }} and quote {{ $order->number }}.</div>
    </div>
@endsection
