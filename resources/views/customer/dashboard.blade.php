<x-shop-layout :title="$category ?: ($search ? 'Search: ' . $search : 'Shop')">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        {{-- Hero (only on the unfiltered first page) --}}
        @if (! $search && ! $category && $products->onFirstPage())
            <section class="relative mb-8 overflow-hidden rounded-3xl bg-gradient-to-br from-brand-600 via-brand-500 to-accent-500 px-6 py-10 text-white sm:px-10">
                <div class="relative z-10 max-w-xl">
                    <p class="text-sm font-semibold uppercase tracking-widest text-white/80">Hi {{ strtok(auth()->user()->name, ' ') }} 👋</p>
                    <h1 class="mt-2 text-3xl font-extrabold leading-tight sm:text-4xl">Everything for your home, delivered fast.</h1>
                    <p class="mt-3 text-white/85">Fresh groceries, the latest gadgets and everyday essentials — cash on delivery anywhere in Bangladesh.</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="#catalogue" class="btn bg-white text-brand-700 hover:bg-brand-50">Start shopping <i class="fa-solid fa-arrow-down"></i></a>
                        <a href="{{ route('orders.index') }}" class="btn bg-white/15 text-white ring-1 ring-white/30 hover:bg-white/25"><i class="fa-solid fa-truck"></i> Track my orders</a>
                    </div>
                </div>
                <i class="fa-solid fa-basket-shopping pointer-events-none absolute -bottom-10 -right-6 text-[13rem] text-white/10 sm:right-10"></i>
            </section>
        @endif

        <div id="catalogue" class="flex flex-col gap-6 lg:flex-row">
            {{-- Category sidebar --}}
            <aside class="lg:w-60 lg:shrink-0">
                <div class="card sticky top-24 p-3">
                    <p class="px-2 pb-2 pt-1 text-xs font-bold uppercase tracking-widest text-slate-400">Categories</p>
                    <nav class="flex gap-1 overflow-x-auto lg:flex-col">
                        <a href="{{ route('customer.dashboard', array_filter(['q' => $search, 'sort' => $sort !== 'popular' ? $sort : null])) }}"
                           @class(['flex shrink-0 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition', 'bg-brand-50 text-brand-700' => ! $category, 'text-slate-600 hover:bg-slate-50' => $category])>
                            <i class="fa-solid fa-border-all w-4 text-center"></i> All products
                            <span class="ml-auto text-xs text-slate-400">{{ $categories->sum() }}</span>
                        </a>
                        @foreach ($categories as $name => $count)
                            <a href="{{ route('customer.dashboard', array_filter(['category' => $name, 'q' => $search, 'sort' => $sort !== 'popular' ? $sort : null])) }}"
                               @class(['flex shrink-0 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition', 'bg-brand-50 text-brand-700' => $category === $name, 'text-slate-600 hover:bg-slate-50' => $category !== $name])>
                                <i class="fa-solid {{ \App\Models\Product::CATEGORY_ICONS[$name] ?? 'fa-tag' }} w-4 text-center"></i> {{ $name }}
                                <span class="ml-auto pl-2 text-xs text-slate-400">{{ $count }}</span>
                            </a>
                        @endforeach
                    </nav>
                </div>
            </aside>

            <div class="min-w-0 flex-1">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-extrabold text-slate-900">
                            @if ($search) Results for “{{ $search }}” @elseif ($category) {{ $category }} @else All products @endif
                        </h2>
                        <p class="text-sm text-slate-500">{{ number_format($products->total()) }} {{ Str::plural('product', $products->total()) }}</p>
                    </div>
                    <form method="GET" class="flex items-center gap-2">
                        @if ($search)<input type="hidden" name="q" value="{{ $search }}">@endif
                        @if ($category)<input type="hidden" name="category" value="{{ $category }}">@endif
                        <label for="sort" class="text-sm text-slate-500">Sort</label>
                        <select id="sort" name="sort" class="form-input w-auto py-1.5" onchange="this.form.submit()">
                            @foreach ($sorts as $key => $label)
                                <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>

                @if ($products->isEmpty())
                    <div class="card">
                        <x-empty-state icon="fa-magnifying-glass" title="No products found" message="Try another search term or browse all categories.">
                            <a href="{{ route('customer.dashboard') }}" class="btn-primary">Browse everything</a>
                        </x-empty-state>
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
                        @foreach ($products as $product)
                            <x-product-card :product="$product" />
                        @endforeach
                    </div>
                    <div class="mt-8">{{ $products->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-shop-layout>
