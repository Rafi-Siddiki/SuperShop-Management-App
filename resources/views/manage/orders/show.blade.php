<x-panel-layout :title="'Order ' . $order->number" :subtitle="(\App\Models\Order::CHANNELS[$order->channel][0] ?? 'Online') . ' · placed ' . $order->created_at->format('d M Y, h:i A') . ' · ' . $order->created_at->diffForHumans()">
    <x-slot:actions>
        <a href="{{ route('manage.orders.index') }}" class="btn-secondary"><i class="fa-solid fa-arrow-left"></i> Orders</a>
        @if ($order->is_pos)
            <a href="{{ route('pos.receipt', $order) }}" target="_blank" class="btn-secondary"><i class="fa-solid fa-receipt"></i> Receipt</a>
        @endif
        <a href="{{ route('invoice.show', $order) }}" class="btn-secondary"><i class="fa-solid fa-file-invoice"></i> Invoice</a>
    </x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="card p-6">
                @include('orders._progress', ['order' => $order])

                @php($next = collect($order->nextStatuses()))
                @if ($next->isNotEmpty())
                    <div class="mt-6 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-5">
                        <span class="mr-auto text-sm text-slate-500">Next step:</span>
                        @foreach ($next->reject(fn ($s) => $s === 'returned') as $status)
                            <form method="POST" action="{{ route('manage.orders.status', $order) }}"
                                  @if ($status === 'cancelled') onsubmit="return confirm('Cancel this order and put its items back in stock?')" @endif>
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $status }}">
                                <button @class(['capitalize', 'btn-danger' => $status === 'cancelled', 'btn-primary' => $status !== 'cancelled'])>
                                    <i class="fa-solid {{ \App\Models\Order::STATUS_ICONS[$status] }}"></i>
                                    {{ ['processing' => 'Confirm & pack', 'shipped' => 'Mark shipped', 'delivered' => 'Mark delivered', 'cancelled' => 'Cancel order'][$status] }}
                                </button>
                            </form>
                        @endforeach
                    </div>

                    @if ($next->contains('returned'))
                        <form method="POST" action="{{ route('manage.orders.status', $order) }}" x-data="{ open: false }"
                              class="mt-4 rounded-xl border border-orange-200 bg-orange-50/50 p-4"
                              onsubmit="return confirm('Record this order as returned?')">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="returned">
                            <button type="button" class="flex w-full items-center justify-between text-left text-sm font-semibold text-orange-800" @click="open = !open">
                                <span><i class="fa-solid fa-arrow-rotate-left mr-1.5"></i>{{ $order->status === 'shipped' ? 'Delivery failed / refused by customer' : 'Customer returned this order' }}</span>
                                <i class="fa-solid fa-chevron-down text-xs transition" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-cloak x-show="open" x-transition class="mt-4 space-y-3">
                                @if ($order->status === 'delivered' && ! $order->isWithinReturnWindow())
                                    <p class="rounded-lg bg-amber-100 px-3 py-2 text-xs font-medium text-amber-800">
                                        <i class="fa-solid fa-triangle-exclamation"></i> Delivered {{ $order->deliveredAt()?->diffForHumans() }} — outside the {{ setting('return_window_days') }}-day return window.
                                    </p>
                                @endif
                                <label class="flex items-start gap-2 text-sm">
                                    <input type="hidden" name="restock" value="0">
                                    <input type="checkbox" name="restock" value="1" checked class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                    <span><span class="font-semibold">Put the items back in stock</span><br><span class="text-xs text-slate-500">Untick if the goods are damaged; write them off separately if needed.</span></span>
                                </label>
                                <input type="text" name="note" maxlength="255" placeholder="Reason (optional), e.g. wrong size, damaged in transit" class="form-input">
                                <button class="btn-secondary border-orange-300 text-orange-800 hover:bg-orange-100">
                                    Record return{{ $order->payment_status === 'paid' ? ' & refund ' . money($order->total_amount) : '' }}
                                </button>
                            </div>
                        </form>
                    @endif
                @endif
            </div>

            <div class="card overflow-hidden">
                <div class="card-header"><h2 class="card-title">Items</h2><span class="text-xs text-slate-500">{{ $order->items->sum('quantity') }} units</span></div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Product</th><th class="!text-right">Price</th><th class="!text-right">Qty</th><th class="!text-right">Total</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($order->items as $item)
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <img src="{{ $item->product?->image_url ?? asset('images/placeholder-product.svg') }}" alt="" class="h-11 w-11 rounded-lg bg-slate-50 object-contain p-0.5">
                                            <div>
                                                <p class="font-semibold text-slate-900">{{ $item->product?->name ?? 'Removed product' }}</p>
                                                <p class="font-mono text-xs text-slate-500">{{ $item->product?->sku }} · {{ $item->product?->barcode }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-right">{{ money($item->price) }}</td>
                                    <td class="text-right">× {{ $item->quantity }}</td>
                                    <td class="text-right font-semibold">{{ money($item->line_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @include('orders._totals', ['order' => $order])
            </div>
        </div>

        <div class="space-y-6">
            <div class="card p-5">
                <h2 class="card-title mb-4">Customer</h2>
                @if ($order->user)
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 font-bold text-white">{{ $order->user->initials }}</span>
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $order->user->name }}</p>
                            <p class="truncate text-sm text-slate-500">{{ $order->user->email }}</p>
                        </div>
                    </div>
                    <a href="{{ route('manage.orders.index', ['q' => $order->user->email]) }}" class="mt-3 inline-block text-xs font-semibold text-brand-600 hover:underline">
                        See all {{ $order->user->orders()->count() }} orders from this customer
                    </a>
                @else
                    <p class="font-semibold">{{ $order->customer_label }}</p>
                    @if ($order->is_pos)<p class="text-sm text-slate-500">In-store customer, no account</p>@endif
                @endif
            </div>

            <div class="card p-5">
                <h2 class="card-title mb-4">{{ $order->is_pos ? 'Sale details' : 'Delivery' }}</h2>
                <dl class="space-y-3 text-sm">
                    @unless ($order->is_pos)
                        <div class="flex gap-3"><dt class="w-5 text-slate-400"><i class="fa-solid fa-location-dot"></i></dt><dd>{{ $order->shipping_address ?? 'No address recorded' }}</dd></div>
                    @endunless
                    @if ($order->phone)
                        <div class="flex gap-3"><dt class="w-5 text-slate-400"><i class="fa-solid fa-phone"></i></dt><dd>{{ $order->phone }}</dd></div>
                    @endif
                    <div class="flex gap-3"><dt class="w-5 text-slate-400"><i class="fa-solid fa-money-bill-wave"></i></dt><dd>{{ $order->payment_method_label }} · <x-payment-badge :status="$order->payment_status" /></dd></div>
                    @if ($order->servedBy)
                        <div class="flex gap-3"><dt class="w-5 text-slate-400"><i class="fa-solid fa-user-tie"></i></dt><dd>Served by {{ $order->servedBy->name }}</dd></div>
                    @endif
                    @if ($order->coupon)
                        <div class="flex gap-3"><dt class="w-5 text-slate-400"><i class="fa-solid fa-ticket"></i></dt><dd>Coupon <span class="font-mono font-semibold">{{ $order->coupon->name }}</span></dd></div>
                    @endif
                    @if ($order->notes)
                        <div class="flex gap-3"><dt class="w-5 text-slate-400"><i class="fa-solid fa-note-sticky"></i></dt><dd class="italic text-slate-600">“{{ $order->notes }}”</dd></div>
                    @endif
                </dl>
            </div>

            <div class="card p-5">
                <h2 class="card-title mb-4">History</h2>
                @include('orders._timeline', ['order' => $order, 'showUsers' => true])
            </div>
        </div>
    </div>
</x-panel-layout>
