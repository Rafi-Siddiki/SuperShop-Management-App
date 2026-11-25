<x-shop-layout :title="'Order ' . $order->number">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <a href="{{ route('orders.index') }}" class="text-sm font-medium text-slate-500 hover:text-brand-600"><i class="fa-solid fa-arrow-left mr-1"></i> My orders</a>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900">Order {{ $order->number }}</h1>
                <p class="text-sm text-slate-500">Placed {{ $order->created_at->format('d M Y \a\t h:i A') }}</p>
            </div>
            <div class="flex gap-2">
                @if ($order->canBeCancelledByCustomer())
                    <form action="{{ route('orders.destroy', $order) }}" method="POST" onsubmit="return confirm('Cancel this order?')">
                        @csrf @method('DELETE')
                        <button class="btn-secondary text-rose-600 hover:bg-rose-50"><i class="fa-solid fa-ban"></i> Cancel order</button>
                    </form>
                @endif
                <a href="{{ route('invoice.show', $order) }}" class="btn-dark"><i class="fa-solid fa-file-arrow-down"></i> Invoice</a>
            </div>
        </div>

        <div class="card mt-6 p-6">
            @include('orders._progress', ['order' => $order])
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <div class="card overflow-hidden lg:col-span-2">
                <div class="card-header"><h2 class="card-title">Items</h2></div>
                <ul class="divide-y divide-slate-100">
                    @foreach ($order->items as $item)
                        <li class="flex items-center gap-4 px-5 py-4">
                            <img src="{{ $item->product?->image_url ?? asset('images/placeholder-product.svg') }}" alt="" class="h-16 w-16 rounded-xl border border-slate-100 bg-slate-50 object-contain p-1">
                            <div class="min-w-0 flex-1">
                                @if ($item->product && ! $item->product->trashed())
                                    <a href="{{ route('shop.product', $item->product) }}" class="font-semibold text-slate-900 hover:text-brand-600">{{ $item->product->name }}</a>
                                @else
                                    <p class="font-semibold text-slate-900">{{ $item->product?->name ?? 'Removed product' }}</p>
                                @endif
                                <p class="text-sm text-slate-500">{{ money($item->price) }} × {{ $item->quantity }}</p>
                            </div>
                            <p class="font-bold">{{ money($item->line_total) }}</p>
                        </li>
                    @endforeach
                </ul>
                @include('orders._totals', ['order' => $order])
            </div>

            <div class="card h-fit p-5">
                <h2 class="card-title mb-4">Delivery</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex gap-3"><dt class="w-5 text-slate-400"><i class="fa-solid fa-user"></i></dt><dd>{{ $order->user->name }}</dd></div>
                    <div class="flex gap-3"><dt class="w-5 text-slate-400"><i class="fa-solid fa-location-dot"></i></dt><dd>{{ $order->shipping_address ?? '—' }}</dd></div>
                    <div class="flex gap-3"><dt class="w-5 text-slate-400"><i class="fa-solid fa-phone"></i></dt><dd>{{ $order->phone ?? '—' }}</dd></div>
                    <div class="flex gap-3"><dt class="w-5 text-slate-400"><i class="fa-solid fa-money-bill-wave"></i></dt><dd>Cash on delivery</dd></div>
                </dl>
                @if ($order->status === 'pending')
                    <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">You can cancel this order until our team starts packing it.</p>
                @endif
            </div>
        </div>
    </div>
</x-shop-layout>
