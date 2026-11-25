{{-- Order progress tracker shared by customer and staff views. --}}
@php
    $steps = ['pending' => 'Placed', 'processing' => 'Packing', 'shipped' => 'On the way', 'delivered' => 'Delivered'];
    $keys = array_keys($steps);
    $current = array_search($order->status, $keys, true);
    $lastChange = $order->relationLoaded('histories') ? $order->histories->last()?->created_at : $order->updated_at;
@endphp

@if ($order->status === 'cancelled')
    <div class="flex items-center gap-4 rounded-xl bg-rose-50 p-4 text-rose-800">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-rose-100"><i class="fa-solid fa-ban text-lg"></i></span>
        <div>
            <p class="font-bold">This order was cancelled</p>
            <p class="text-sm text-rose-700">Cancelled {{ $lastChange?->diffForHumans() }}. The items were returned to stock.</p>
        </div>
    </div>
@elseif ($order->status === 'returned')
    <div class="flex items-center gap-4 rounded-xl bg-orange-50 p-4 text-orange-800">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-orange-100"><i class="fa-solid fa-arrow-rotate-left text-lg"></i></span>
        <div>
            <p class="font-bold">This order was returned</p>
            <p class="text-sm text-orange-700">Returned {{ $lastChange?->diffForHumans() }}.@if ($order->payment_status === 'refunded') The payment was refunded.@endif</p>
        </div>
    </div>
@elseif ($order->is_pos)
    <div class="flex items-center gap-4 rounded-xl bg-emerald-50 p-4 text-emerald-800">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-100"><i class="fa-solid fa-cash-register text-lg"></i></span>
        <div>
            <p class="font-bold">Sold at the counter</p>
            <p class="text-sm text-emerald-700">{{ $order->created_at->format('d M Y, h:i A') }}@if ($order->servedBy) · served by {{ $order->servedBy->name }}@endif · paid by {{ $order->payment_method_label }}</p>
        </div>
    </div>
@else
    <ol class="grid grid-cols-4">
        @foreach ($steps as $key => $label)
            @php($i = $loop->index)
            <li class="relative flex flex-col items-center text-center">
                @if (! $loop->first)
                    <span @class(['absolute right-1/2 top-5 h-1 w-full -translate-y-1/2', 'bg-gradient-to-r from-brand-500 to-accent-500' => $i <= $current, 'bg-slate-200' => $i > $current])></span>
                @endif
                <span @class([
                    'relative z-10 flex h-10 w-10 items-center justify-center rounded-full text-sm ring-4 ring-white',
                    'bg-gradient-to-br from-brand-500 to-accent-500 text-white shadow' => $i <= $current,
                    'bg-slate-100 text-slate-400' => $i > $current,
                ])>
                    <i class="fa-solid {{ \App\Models\Order::STATUS_ICONS[$key] }}"></i>
                </span>
                <span @class(['mt-2 text-xs font-semibold sm:text-sm', 'text-slate-900' => $i <= $current, 'text-slate-400' => $i > $current])>{{ $label }}</span>
            </li>
        @endforeach
    </ol>
@endif
