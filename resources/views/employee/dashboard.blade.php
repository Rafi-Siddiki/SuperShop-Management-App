<x-panel-layout title="Dashboard" :subtitle="'Good ' . (now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening')) . ', ' . auth()->user()->name . '. Here is today\'s work queue.'">
    <x-slot:actions>
        <a href="{{ route('manage.orders.index', ['status' => 'pending', 'channel' => 'online']) }}" class="btn-secondary"><i class="fa-solid fa-play"></i> Process orders</a>
        <a href="{{ route('pos.index') }}" class="btn-primary"><i class="fa-solid fa-cash-register"></i> Open till</a>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Waiting for confirmation" :value="$counts['pending'] ?? 0" icon="fa-hourglass-half" tone="amber" :href="route('manage.orders.index', ['status' => 'pending'])" />
        <x-stat-card label="Being packed" :value="$counts['processing'] ?? 0" icon="fa-box-open" tone="sky" :href="route('manage.orders.index', ['status' => 'processing'])" />
        <x-stat-card label="Out for delivery" :value="$counts['shipped'] ?? 0" icon="fa-truck-fast" tone="violet" :href="route('manage.orders.index', ['status' => 'shipped'])"
                     :hint="$shippedToday . ' shipped today'" />
        <x-stat-card label="My till sales today" :value="money($mySalesToday->total)" icon="fa-cash-register" tone="emerald"
                     :hint="$mySalesToday->count . ' ' . Str::plural('sale', $mySalesToday->count)" :href="route('pos.index')" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card overflow-hidden xl:col-span-2">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Work queue</h2>
                    <p class="text-xs text-slate-500">Orders to confirm first, then orders to ship — oldest first</p>
                </div>
                <a href="{{ route('manage.orders.index') }}" class="btn-ghost btn-sm">All orders <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Order</th><th>Customer</th><th>Waiting</th><th>Status</th><th class="!text-right">Next step</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($queue as $order)
                            <tr>
                                <td><a href="{{ route('manage.orders.show', $order) }}" class="font-semibold hover:text-brand-600">{{ $order->number }}</a>
                                    <p class="text-xs text-slate-500">{{ $order->items_count }} {{ Str::plural('item', $order->items_count) }} · {{ money($order->total_amount) }}</p></td>
                                <td>{{ $order->customer_label }}</td>
                                <td @class(['whitespace-nowrap', 'font-semibold text-rose-600' => $order->created_at->lt(now()->subDay())])>{{ $order->created_at->diffForHumans(null, true) }}</td>
                                <td><x-status-badge :status="$order->status" /></td>
                                <td class="text-right">
                                    @php($next = $order->status === 'pending' ? 'processing' : 'shipped')
                                    <form method="POST" action="{{ route('manage.orders.status', $order) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $next }}">
                                        <button class="btn-dark btn-sm whitespace-nowrap">
                                            <i class="fa-solid {{ \App\Models\Order::STATUS_ICONS[$next] }}"></i> {{ $next === 'processing' ? 'Confirm' : 'Ship' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty-state icon="fa-mug-hot" title="All caught up!" message="There are no orders waiting right now." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">Low stock alerts</h2>
                <a href="{{ route('inventory.index', ['filter' => 'reorder']) }}" class="btn-ghost btn-sm">Stock <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($lowStock as $product)
                    <li class="flex items-center gap-3 px-5 py-2.5">
                        <img src="{{ $product->image_url }}" alt="" class="h-9 w-9 rounded-lg bg-slate-50 object-contain p-0.5">
                        <p class="min-w-0 flex-1 truncate text-sm">{{ $product->name }}</p>
                        <span @class(['badge', 'bg-rose-50 text-rose-700 ring-rose-600/20' => $product->stock_quantity <= 0, 'bg-amber-50 text-amber-700 ring-amber-600/20' => $product->stock_quantity > 0])>
                            {{ $product->stock_quantity <= 0 ? 'Out' : $product->stock_quantity . ' left' }}
                        </span>
                    </li>
                @empty
                    <li><x-empty-state icon="fa-circle-check" title="Stock levels look healthy" /></li>
                @endforelse
            </ul>
            <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500"><i class="fa-solid fa-circle-info mr-1"></i>Deliveries can be received on the Stock page or by scanning.</p>
        </div>
    </div>
</x-panel-layout>
