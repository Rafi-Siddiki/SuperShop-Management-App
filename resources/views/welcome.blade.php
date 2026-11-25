<x-shop-layout>
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-slate-900">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_rgba(255,137,56,0.35),_transparent_55%),radial-gradient(ellipse_at_bottom_left,_rgba(229,29,29,0.45),_transparent_55%)]"></div>
        <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-24">
            <div>
                <span class="badge bg-white/10 text-accent-400 ring-white/20"><i class="fa-solid fa-bolt"></i> Same-day delivery in Dhaka</span>
                <h1 class="mt-5 text-4xl font-extrabold leading-tight tracking-tight text-white sm:text-5xl">
                    Your neighbourhood <span class="bg-gradient-to-r from-brand-400 to-accent-400 bg-clip-text text-transparent">supershop</span>, online.
                </h1>
                <p class="mt-5 max-w-lg text-lg text-slate-300">Fresh groceries, phones and laptops, fashion, beauty and home essentials — at fair prices, paid in cash at your door.</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn-primary px-6 py-3 text-base">Go to {{ auth()->user()->isCustomer() ? 'the shop' : 'your dashboard' }} <i class="fa-solid fa-arrow-right"></i></a>
                    @else
                        <a href="{{ route('register') }}" class="btn-primary px-6 py-3 text-base">Start shopping <i class="fa-solid fa-arrow-right"></i></a>
                        <a href="{{ route('login') }}" class="btn bg-white/10 px-6 py-3 text-base text-white ring-1 ring-white/20 hover:bg-white/20">I have an account</a>
                    @endauth
                </div>
                <dl class="mt-10 grid max-w-md grid-cols-3 gap-6 text-white">
                    <div><dt class="text-xs uppercase tracking-wider text-slate-400">Products</dt><dd class="mt-1 text-2xl font-extrabold">{{ $categories->sum('product_count') }}+</dd></div>
                    <div><dt class="text-xs uppercase tracking-wider text-slate-400">Categories</dt><dd class="mt-1 text-2xl font-extrabold">{{ $categories->count() }}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wider text-slate-400">Delivery</dt><dd class="mt-1 text-2xl font-extrabold">1–3 days</dd></div>
                </dl>
            </div>

            {{-- Product collage --}}
            <div class="relative hidden lg:block">
                <div class="grid grid-cols-3 gap-4">
                    @foreach ($bestSellers->concat($newArrivals)->unique('id')->take(6) as $i => $product)
                        <div @class(['rounded-2xl bg-white p-3 shadow-2xl shadow-black/30', 'translate-y-8' => $i % 3 === 1])>
                            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="aspect-square w-full object-contain">
                            <p class="mt-2 truncate text-xs font-semibold text-slate-700">{{ $product->name }}</p>
                            <p class="text-sm font-extrabold text-brand-600">{{ money($product->price) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Perks --}}
    <section class="border-b border-slate-200 bg-white">
        <div class="mx-auto grid max-w-7xl grid-cols-2 gap-6 px-4 py-6 sm:px-6 lg:grid-cols-4 lg:px-8">
            @foreach ([['fa-truck-fast', 'Fast delivery', 'Across all 64 districts'], ['fa-money-bill-wave', 'Cash on delivery', 'Pay when it arrives'], ['fa-rotate-left', 'Easy returns', setting('return_window_days') . ' days, no questions'], ['fa-headset', 'Real support', setting('store_hours') ?: setting('store_phone')]] as [$icon, $title, $text])
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><i class="fa-solid {{ $icon }}"></i></span>
                    <div><p class="text-sm font-bold text-slate-900">{{ $title }}</p><p class="text-xs text-slate-500">{{ $text }}</p></div>
                </div>
            @endforeach
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if ($featuredCoupon)
            <div class="mt-10 flex flex-wrap items-center justify-between gap-4 rounded-2xl border-2 border-dashed border-brand-300 bg-brand-50 px-6 py-5">
                <div>
                    <p class="text-lg font-extrabold text-brand-800">Save {{ rtrim(rtrim($featuredCoupon->discount, '0'), '.') }}% on your order</p>
                    <p class="text-sm text-brand-700">Use the code at checkout · valid until {{ $featuredCoupon->expiry_date->format('d M Y') }}</p>
                </div>
                <span class="rounded-xl bg-white px-5 py-2.5 font-mono text-xl font-extrabold tracking-widest text-brand-700 shadow-sm">{{ $featuredCoupon->name }}</span>
            </div>
        @endif

        {{-- Categories --}}
        <section class="mt-12">
            <h2 class="text-2xl font-extrabold text-slate-900">Shop by category</h2>
            <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-8">
                @foreach ($categories as $cat)
                    <a href="{{ auth()->user()?->isCustomer() ? route('customer.dashboard', ['category' => $cat->category]) : route('login') }}"
                       class="card group flex flex-col items-center gap-2 p-4 text-center transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-lift">
                        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-brand-50 to-accent-400/20 text-brand-600 transition group-hover:from-brand-500 group-hover:to-accent-500 group-hover:text-white">
                            <i class="fa-solid {{ \App\Models\Product::CATEGORY_ICONS[$cat->category] ?? 'fa-tag' }} text-lg"></i>
                        </span>
                        <span class="text-sm font-semibold text-slate-800">{{ $cat->category }}</span>
                        <span class="text-xs text-slate-400">{{ $cat->product_count }} items</span>
                    </a>
                @endforeach
            </div>
        </section>

        @foreach (['Best sellers' => $bestSellers, 'New arrivals' => $newArrivals] as $heading => $list)
            @continue($list->isEmpty())
            <section class="mt-14">
                <div class="flex items-end justify-between">
                    <h2 class="text-2xl font-extrabold text-slate-900">{{ $heading }}</h2>
                    <a href="{{ auth()->user()?->isCustomer() ? route('customer.dashboard', $heading === 'New arrivals' ? ['sort' => 'newest'] : []) : route('login') }}"
                       class="text-sm font-semibold text-brand-600 hover:underline">See all <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($list as $product)
                        <x-product-card :product="$product" :guest="! auth()->user()?->isCustomer()" />
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-shop-layout>
