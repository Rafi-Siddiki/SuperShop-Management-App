<x-shop-layout title="Your cart">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <h1 class="mb-6 text-2xl font-extrabold text-slate-900">Your cart
            @if ($cart->items->isNotEmpty())<span class="text-base font-semibold text-slate-400">({{ $cart->item_count }} {{ Str::plural('item', $cart->item_count) }})</span>@endif
        </h1>

        @if ($cart->items->isEmpty())
            <div class="card">
                <x-empty-state icon="fa-cart-shopping" title="Your cart is empty" message="Browse the shop and add a few things you love.">
                    <a href="{{ route('customer.dashboard') }}" class="btn-primary"><i class="fa-solid fa-store"></i> Start shopping</a>
                </x-empty-state>
            </div>
        @else
            <div class="grid gap-6 lg:grid-cols-3">
                {{-- Items --}}
                <div class="card h-fit divide-y divide-slate-100 lg:col-span-2">
                    @foreach ($cart->items as $item)
                        @php($product = $item->product)
                        <div class="flex gap-4 p-4 sm:p-5">
                            <a href="{{ route('shop.product', $product) }}" class="shrink-0">
                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="h-20 w-20 rounded-xl border border-slate-100 bg-slate-50 object-contain p-1 sm:h-24 sm:w-24">
                            </a>
                            <div class="flex min-w-0 flex-1 flex-col">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <a href="{{ route('shop.product', $product) }}" class="line-clamp-2 font-semibold text-slate-900 hover:text-brand-600">{{ $product->name }}</a>
                                        <p class="text-sm text-slate-500">{{ money($product->price) }} each</p>
                                        @if ($item->quantity > $product->stock_quantity)
                                            <p class="mt-1 text-xs font-semibold text-rose-600"><i class="fa-solid fa-triangle-exclamation"></i> Only {{ $product->stock_quantity }} in stock — please reduce the quantity.</p>
                                        @endif
                                    </div>
                                    <p class="whitespace-nowrap font-bold text-slate-900">{{ money($product->price * $item->quantity) }}</p>
                                </div>
                                <div class="mt-auto flex items-center justify-between pt-3">
                                    <div class="flex items-center rounded-lg border border-slate-200">
                                        <form action="{{ route('cart.update', $item) }}" method="POST">
                                            @csrf <input type="hidden" name="action" value="decrease">
                                            <button class="px-3 py-1.5 text-slate-500 hover:text-slate-900" aria-label="Decrease quantity"><i class="fa-solid {{ $item->quantity > 1 ? 'fa-minus' : 'fa-trash-can' }} text-xs"></i></button>
                                        </form>
                                        <span class="w-8 text-center text-sm font-semibold">{{ $item->quantity }}</span>
                                        <form action="{{ route('cart.update', $item) }}" method="POST">
                                            @csrf <input type="hidden" name="action" value="increase">
                                            <button class="px-3 py-1.5 text-slate-500 hover:text-slate-900 disabled:opacity-30" aria-label="Increase quantity" @disabled($item->quantity >= $product->stock_quantity)><i class="fa-solid fa-plus text-xs"></i></button>
                                        </form>
                                    </div>
                                    <form action="{{ route('cart.remove', $item) }}" method="POST">
                                        @csrf
                                        <button class="text-sm font-medium text-slate-400 hover:text-rose-600"><i class="fa-solid fa-xmark mr-1"></i>Remove</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Summary + checkout --}}
                <div class="space-y-4">
                    <div class="card p-5">
                        <h2 class="card-title mb-4">Order summary</h2>

                        @if ($coupon)
                            <div @class(['mb-4 rounded-xl border px-3 py-2.5 text-sm', 'border-emerald-200 bg-emerald-50' => ! $couponProblem, 'border-amber-200 bg-amber-50' => $couponProblem])>
                                <div class="flex items-center justify-between">
                                    <span @class(['text-emerald-800' => ! $couponProblem, 'text-amber-800' => $couponProblem])><i class="fa-solid fa-ticket mr-1.5"></i><span class="font-mono font-bold">{{ $coupon->name }}</span> · {{ $coupon->discount_label }} off</span>
                                    <form action="{{ route('cart.removeCoupon') }}" method="POST">
                                        @csrf
                                        <button class="text-slate-500 hover:text-rose-600" aria-label="Remove coupon"><i class="fa-solid fa-xmark"></i></button>
                                    </form>
                                </div>
                                @if ($couponProblem)
                                    <p class="mt-1 text-xs font-medium text-amber-800">{{ $couponProblem }} It will not be applied.</p>
                                @endif
                            </div>
                        @else
                            <form action="{{ route('cart.applyCoupon') }}" method="POST" class="mb-4 flex gap-2">
                                @csrf
                                <input type="text" name="coupon_code" placeholder="Coupon code" required class="form-input font-mono uppercase" value="{{ old('coupon_code') }}">
                                <button class="btn-secondary">Apply</button>
                            </form>
                        @endif

                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd>{{ money($subtotal) }}</dd></div>
                            @if ($discount > 0)
                                <div class="flex justify-between text-emerald-700"><dt>Discount</dt><dd>− {{ money($discount) }}</dd></div>
                            @endif
                            <div class="flex justify-between"><dt class="text-slate-500">Delivery</dt><dd @class(['text-emerald-700' => $deliveryFee == 0])>{{ $deliveryFee > 0 ? money($deliveryFee) : 'Free' }}</dd></div>
                            <div class="flex justify-between border-t border-slate-100 pt-3 text-lg font-extrabold text-slate-900"><dt>Total</dt><dd>{{ money($total) }}</dd></div>
                        </dl>
                        @if ($toFreeDelivery > 0)
                            @php($threshold = setting('free_delivery_threshold'))
                            <div class="mt-4 rounded-xl bg-slate-50 p-3 text-xs text-slate-600">
                                <p>Add <span class="font-bold text-slate-900">{{ money($toFreeDelivery) }}</span> more for <span class="font-semibold text-emerald-700">free delivery</span>.</p>
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200">
                                    <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-accent-500" style="width: {{ min(100, max(0, ($threshold - $toFreeDelivery) / $threshold * 100)) }}%"></div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <form action="{{ route('cart.checkout') }}" method="POST" class="card p-5" x-data="{ busy: false }" @submit="busy = true">
                        @csrf
                        <h2 class="card-title mb-4">Delivery details</h2>
                        <div class="space-y-4">
                            <div>
                                <label for="phone" class="form-label">Phone number</label>
                                <input type="tel" id="phone" name="phone" required placeholder="01XXXXXXXXX" class="form-input"
                                       value="{{ old('phone', $lastOrder?->phone) }}">
                                @error('phone')<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="shipping_address" class="form-label">Delivery address</label>
                                <textarea id="shipping_address" name="shipping_address" rows="3" required placeholder="House, road, area, city" class="form-input">{{ old('shipping_address', $lastOrder?->shipping_address) }}</textarea>
                                @error('shipping_address')<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="notes" class="form-label">Note for the rider <span class="font-normal text-slate-400">(optional)</span></label>
                                <input type="text" id="notes" name="notes" maxlength="500" placeholder="e.g. call before coming, gate code…" class="form-input" value="{{ old('notes') }}">
                            </div>
                            <p class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                <i class="fa-solid fa-money-bill-wave text-emerald-600"></i> Pay with cash when your order arrives.
                            </p>
                        </div>
                        <button class="btn-primary mt-5 w-full py-3 text-base" :disabled="busy">
                            <i class="fa-solid" :class="busy ? 'fa-spinner fa-spin' : 'fa-lock'"></i> Place order · {{ money($total) }}
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-shop-layout>
