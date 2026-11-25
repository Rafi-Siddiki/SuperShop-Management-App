<dl class="space-y-2 border-t border-slate-100 bg-slate-50/60 px-5 py-4 text-sm">
    <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd>{{ money($order->items_subtotal) }}</dd></div>
    @if ($order->discount > 0)
        <div class="flex justify-between text-emerald-700">
            <dt>Discount @if ($order->coupon)<span class="font-mono text-xs">({{ $order->coupon->name }})</span>@endif</dt>
            <dd>− {{ money($order->discount) }}</dd>
        </div>
    @endif
    @unless ($order->is_pos)
        <div class="flex justify-between">
            <dt class="text-slate-500">Delivery</dt>
            <dd @class(['text-emerald-700' => (float) $order->delivery_fee == 0])>{{ (float) $order->delivery_fee > 0 ? money($order->delivery_fee) : 'Free' }}</dd>
        </div>
    @endunless
    <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-extrabold text-slate-900"><dt>Total</dt><dd>{{ money($order->total_amount) }}</dd></div>
    @if ($order->is_pos && $order->payment_method === 'cash')
        <div class="flex justify-between text-slate-500"><dt>Cash received</dt><dd>{{ money($order->amount_tendered) }}</dd></div>
        <div class="flex justify-between text-slate-500"><dt>Change given</dt><dd>{{ money($order->change_due) }}</dd></div>
    @endif
    <div class="flex items-center justify-between pt-1 text-xs">
        <dt class="text-slate-500">Payment · {{ $order->payment_method_label }}</dt>
        <dd><x-payment-badge :status="$order->payment_status" /></dd>
    </div>
</dl>
