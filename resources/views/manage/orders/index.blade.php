<x-panel-layout title="Orders" subtitle="Online orders to fulfil and in-store sales">
    <x-slot:actions>
        <a href="{{ route('pos.index') }}" class="btn-primary"><i class="fa-solid fa-cash-register"></i> New in-store sale</a>
    </x-slot:actions>

    @php($base = array_filter(['q' => $search, 'channel' => $channel]))

    {{-- Channel switch --}}
    <div class="mb-3 inline-flex rounded-lg border border-slate-200 bg-white p-0.5 shadow-sm">
        @foreach (['' => ['All', 'fa-layer-group']] + \App\Models\Order::CHANNELS as $key => [$label, $icon])
            <a href="{{ route('manage.orders.index', array_filter(['q' => $search, 'status' => $status, 'channel' => $key ?: null])) }}"
               @class(['flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold transition', 'bg-slate-900 text-white' => ($channel ?? '') === $key, 'text-slate-600 hover:bg-slate-100' => ($channel ?? '') !== $key])>
                <i class="fa-solid {{ $icon }}"></i> {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Status tabs --}}
    <div class="mb-4 flex gap-2 overflow-x-auto pb-1">
        <a href="{{ route('manage.orders.index', $base) }}"
           @class(['btn btn-sm whitespace-nowrap', 'bg-slate-900 text-white' => ! $status, 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' => $status])>
            All <span class="rounded-full bg-white/20 px-1.5 text-[11px]">{{ number_format($counts->sum()) }}</span>
        </a>
        @foreach (\App\Models\Order::STATUSES as $s)
            <a href="{{ route('manage.orders.index', $base + ['status' => $s]) }}"
               @class(['btn btn-sm whitespace-nowrap capitalize', 'bg-slate-900 text-white' => $status === $s, 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' => $status !== $s])>
                <i class="fa-solid {{ \App\Models\Order::STATUS_ICONS[$s] }} text-[11px]"></i> {{ $s }}
                <span @class(['rounded-full px-1.5 text-[11px]', 'bg-white/20' => $status === $s, 'bg-slate-100' => $status !== $s])>{{ number_format($counts[$s] ?? 0) }}</span>
            </a>
        @endforeach
    </div>

    <div class="card overflow-hidden">
        <form method="GET" class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
            @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            @if ($channel)<input type="hidden" name="channel" value="{{ $channel }}">@endif
            <div class="relative min-w-[14rem] flex-1">
                <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="search" name="q" value="{{ $search }}" placeholder="Order number (or scan a receipt), customer, email or phone" class="form-input pl-9">
            </div>
            <button class="btn-dark">Search</button>
            @if ($search)
                <a href="{{ route('manage.orders.index', array_filter(['status' => $status, 'channel' => $channel])) }}" class="btn-ghost">Clear</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Order</th><th>Customer</th><th>Placed</th><th class="!text-right">Total</th><th>Payment</th><th>Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($orders as $order)
                        <tr>
                            <td>
                                <a href="{{ route('manage.orders.show', $order) }}" class="font-semibold text-slate-900 hover:text-brand-600">{{ $order->number }}</a>
                                <p class="text-xs text-slate-500"><i class="fa-solid {{ \App\Models\Order::CHANNELS[$order->channel][1] ?? 'fa-globe' }} mr-1"></i>{{ \App\Models\Order::CHANNELS[$order->channel][0] ?? '' }} · {{ $order->items_count }} {{ Str::plural('item', $order->items_count) }}</p>
                            </td>
                            <td>
                                <p class="font-medium text-slate-800">{{ $order->customer_label }}</p>
                                <p class="text-xs text-slate-500">{{ $order->user?->email ?? ($order->servedBy ? 'Served by ' . $order->servedBy->name : '') }}</p>
                            </td>
                            <td class="whitespace-nowrap">
                                <p>{{ $order->created_at->format('d M Y') }}</p>
                                <p class="text-xs text-slate-500">{{ $order->created_at->format('h:i A') }}</p>
                            </td>
                            <td class="text-right font-semibold">{{ money($order->total_amount) }}</td>
                            <td>
                                <x-payment-badge :status="$order->payment_status" />
                                <p class="mt-0.5 text-xs text-slate-500">{{ $order->payment_method_label }}</p>
                            </td>
                            <td><x-status-badge :status="$order->status" /></td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @foreach ($order->nextStatuses() as $next)
                                        @continue(in_array($next, ['cancelled', 'returned'], true))
                                        <form method="POST" action="{{ route('manage.orders.status', $order) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $next }}">
                                            <button class="btn-secondary btn-sm whitespace-nowrap" title="Mark as {{ $next }}">
                                                <i class="fa-solid {{ \App\Models\Order::STATUS_ICONS[$next] }}"></i> {{ ['processing' => 'Confirm', 'shipped' => 'Ship', 'delivered' => 'Deliver'][$next] }}
                                            </button>
                                        </form>
                                    @endforeach
                                    <a href="{{ route('manage.orders.show', $order) }}" class="btn-ghost btn-sm" title="View"><i class="fa-solid fa-chevron-right"></i></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="fa-receipt" title="No orders found" message="Nothing matches this filter right now." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($orders->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $orders->links() }}</div>
        @endif
    </div>
</x-panel-layout>
