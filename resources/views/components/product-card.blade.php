@props(['product', 'guest' => false])

<div class="group card flex flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-lift">
    <a href="{{ $guest ? route('login') : route('shop.product', $product) }}" class="relative block aspect-square overflow-hidden bg-slate-50">
        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy"
             class="h-full w-full object-contain p-4 transition duration-300 group-hover:scale-105">
        @if ($product->is_out_of_stock)
            <span class="badge absolute left-3 top-3 bg-slate-900/80 text-white ring-0">Sold out</span>
        @elseif ($product->is_low_stock)
            <span class="badge absolute left-3 top-3 bg-amber-50 text-amber-700 ring-amber-600/20">Only {{ $product->stock_quantity }} left</span>
        @elseif ($product->date && $product->date->gt(now()->subDays(21)))
            <span class="badge absolute left-3 top-3 bg-brand-600 text-white ring-0">New</span>
        @endif
    </a>
    <div class="flex flex-1 flex-col p-4">
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $product->category }}</p>
        <a href="{{ $guest ? route('login') : route('shop.product', $product) }}" class="mt-1 line-clamp-2 text-sm font-semibold leading-snug text-slate-900 hover:text-brand-600">{{ $product->name }}</a>
        <div class="mt-auto flex items-end justify-between gap-2 pt-3">
            <p class="text-lg font-extrabold text-slate-900">{{ money($product->price) }}</p>
            @if ($guest)
                <a href="{{ route('login') }}" class="btn-dark btn-sm" aria-label="Sign in to buy"><i class="fa-solid fa-cart-plus"></i></a>
            @elseif (! $product->is_out_of_stock)
                <form action="{{ route('cart.add', $product) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn-dark btn-sm" aria-label="Add {{ $product->name }} to cart"><i class="fa-solid fa-cart-plus"></i> Add</button>
                </form>
            @endif
        </div>
    </div>
</div>
