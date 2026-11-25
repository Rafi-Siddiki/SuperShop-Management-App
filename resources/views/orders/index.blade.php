<x-shop-layout title="My orders">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-extrabold text-slate-900">My orders</h1>
        <p class="mt-1 text-sm text-slate-500">Track deliveries, download invoices and cancel orders that haven't been packed yet.</p>

        <div class="mb-5 mt-5 flex gap-2 overflow-x-auto pb-1">
            <a href="{{ route('orders.index') }}" @class(['btn btn-sm whitespace-nowrap', 'bg-slate-900 text-white' => ! $status, 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50' => $status])>
                All <span class="opacity-70">{{ $counts->sum() }}</span>
            </a>
            @foreach (\App\Models\Order::STATUSES as $s)
                @continue(! ($counts[$s] ?? 0))
                <a href="{{ route('orders.index', ['status' => $s]) }}" @class(['btn btn-sm whitespace-nowrap capitalize', 'bg-slate-900 text-white' => $status === $s, 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50' => $status !== $s])>
                    {{ $s }} <span class="opacity-70">{{ $counts[$s] }}</span>
                </a>
            @endforeach
        </div>

        <div class="space-y-4">
            @forelse ($orders as $order)
                <div class="card overflow-hidden">
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 border-b border-slate-100 bg-slate-50/60 px-5 py-3 text-sm">
                        <div><p class="text-xs text-slate-500">Order</p><p class="font-bold text-slate-900">{{ $order->number }}</p></div>
                        <div><p class="text-xs text-slate-500">Placed</p><p class="font-medium">{{ $order->created_at->format('d M Y') }}</p></div>
                        <div><p class="text-xs text-slate-500">Total</p><p class="font-bold">{{ money($order->total_amount) }}</p></div>
                        <div class="ml-auto"><x-status-badge :status="$order->status" /></div>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 px-5 py-4">
                        <div class="flex -space-x-3">
                            @foreach ($order->items->take(4) as $item)
                                <img src="{{ $item->product?->image_url ?? asset('images/placeholder-product.svg') }}" alt="{{ $item->product?->name }}" title="{{ $item->product?->name }}"
                                     class="h-14 w-14 rounded-xl border-2 border-white bg-slate-50 object-contain p-1 shadow-sm">
                            @endforeach
                            @if ($order->items->count() > 4)
                                <span class="flex h-14 w-14 items-center justify-center rounded-xl border-2 border-white bg-slate-100 text-sm font-bold text-slate-500">+{{ $order->items->count() - 4 }}</span>
                            @endif
                        </div>
                        <p class="min-w-0 flex-1 text-sm text-slate-600">
                            {{ $order->items->map(fn ($i) => $i->product?->name)->filter()->take(2)->implode(', ') }}@if ($order->items->count() > 2) and {{ $order->items->count() - 2 }} more @endif
                        </p>
                        <div class="flex gap-2">
                            <a href="{{ route('invoice.show', $order) }}" class="btn-secondary btn-sm"><i class="fa-solid fa-file-invoice"></i> Invoice</a>
                            <a href="{{ route('orders.show', $order) }}" class="btn-dark btn-sm">Details <i class="fa-solid fa-chevron-right"></i></a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card">
                    <x-empty-state icon="fa-truck" title="No orders yet" message="When you place an order it will show up here.">
                        <a href="{{ route('customer.dashboard') }}" class="btn-primary"><i class="fa-solid fa-store"></i> Start shopping</a>
                    </x-empty-state>
                </div>
            @endforelse
        </div>

        <div class="mt-6">{{ $orders->links() }}</div>
    </div>
</x-shop-layout>
