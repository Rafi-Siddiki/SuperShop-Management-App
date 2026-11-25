<x-shop-layout :title="$product->name">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <nav class="mb-5 flex items-center gap-2 text-sm text-slate-500">
            <a href="{{ route('customer.dashboard') }}" class="hover:text-brand-600">Shop</a>
            <i class="fa-solid fa-chevron-right text-[10px]"></i>
            <a href="{{ route('customer.dashboard', ['category' => $product->category]) }}" class="hover:text-brand-600">{{ $product->category }}</a>
            <i class="fa-solid fa-chevron-right text-[10px]"></i>
            <span class="truncate text-slate-800">{{ $product->name }}</span>
        </nav>

        <div class="grid gap-8 lg:grid-cols-2">
            <div class="card flex aspect-square items-center justify-center overflow-hidden bg-white p-8">
                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="max-h-full max-w-full object-contain">
            </div>

            <div class="flex flex-col">
                <span class="badge w-fit bg-slate-100 text-slate-600 ring-slate-500/10"><i class="fa-solid {{ $product->category_icon }}"></i>{{ $product->category }}</span>
                <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">{{ $product->name }}</h1>
                @if ($unitsSold > 0)
                    <p class="mt-2 text-sm text-slate-500"><i class="fa-solid fa-fire mr-1 text-accent-500"></i>{{ number_format($unitsSold) }} sold</p>
                @endif

                <p class="mt-5 text-4xl font-extrabold text-slate-900">{{ money($product->price) }}</p>

                <div class="mt-3">
                    @if ($product->is_out_of_stock)
                        <span class="badge bg-rose-50 text-rose-700 ring-rose-600/20"><i class="fa-solid fa-circle-xmark"></i> Out of stock</span>
                    @elseif ($product->is_low_stock)
                        <span class="badge bg-amber-50 text-amber-700 ring-amber-600/20"><i class="fa-solid fa-triangle-exclamation"></i> Hurry — only {{ $product->stock_quantity }} left</span>
                    @else
                        <span class="badge bg-emerald-50 text-emerald-700 ring-emerald-600/20"><i class="fa-solid fa-circle-check"></i> In stock</span>
                    @endif
                </div>

                <p class="mt-6 leading-relaxed text-slate-600">{{ $product->description }}</p>

                @unless ($product->is_out_of_stock)
                    <form action="{{ route('cart.add', $product) }}" method="POST" class="mt-8 flex flex-wrap items-center gap-3"
                          x-data="{ qty: 1, max: {{ min(99, $product->stock_quantity) }} }">
                        @csrf
                        <div class="flex items-center rounded-lg border border-slate-300 bg-white">
                            <button type="button" class="px-3 py-2.5 text-slate-500 hover:text-slate-900" @click="qty = Math.max(1, qty - 1)" aria-label="Decrease"><i class="fa-solid fa-minus"></i></button>
                            <input type="number" name="quantity" x-model.number="qty" min="1" :max="max" class="w-14 border-0 text-center font-semibold focus:ring-0">
                            <button type="button" class="px-3 py-2.5 text-slate-500 hover:text-slate-900" @click="qty = Math.min(max, qty + 1)" aria-label="Increase"><i class="fa-solid fa-plus"></i></button>
                        </div>
                        <button class="btn-primary flex-1 py-3 text-base sm:flex-none sm:px-8"><i class="fa-solid fa-cart-plus"></i> Add to cart</button>
                    </form>
                @endunless

                <div class="mt-8 grid grid-cols-3 gap-3 border-t border-slate-200 pt-6 text-center text-xs text-slate-600">
                    <div><i class="fa-solid fa-truck-fast mb-1.5 block text-lg text-brand-500"></i>1–3 day delivery</div>
                    <div><i class="fa-solid fa-money-bill-wave mb-1.5 block text-lg text-brand-500"></i>Cash on delivery</div>
                    <div><i class="fa-solid fa-rotate-left mb-1.5 block text-lg text-brand-500"></i>7-day returns</div>
                </div>
            </div>
        </div>

        @if ($related->isNotEmpty())
            <section class="mt-14">
                <h2 class="mb-5 text-xl font-extrabold text-slate-900">More in {{ $product->category }}</h2>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    @foreach ($related as $item)
                        <x-product-card :product="$item" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-shop-layout>
