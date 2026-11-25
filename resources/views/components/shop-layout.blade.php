@props(['title' => null])

@php
    $user = auth()->user();
    $cartCount = $user && $user->isCustomer()
        ? (int) \App\Models\CartItem::whereHas('cart', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))->sum('quantity')
        : 0;
    $shopHome = $user && $user->isCustomer() ? route('customer.dashboard') : route('home');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-slate-50">
<head>
    @include('partials.head', ['title' => $title])
    @stack('head')
</head>
<body class="flex min-h-screen flex-col font-sans text-slate-800 antialiased" x-data="{ menu: false }">

{{-- Announcement bar --}}
<div class="bg-slate-900 text-xs text-slate-300">
    <div class="mx-auto flex max-w-7xl items-center justify-center gap-6 px-4 py-2 sm:justify-between">
        <p><i class="fa-solid fa-truck-fast mr-1.5 text-accent-400"></i>@if (setting('free_delivery_threshold') > 0) Free delivery on orders over {{ money(setting('free_delivery_threshold')) }} @else Delivery {{ money(setting('delivery_fee')) }} anywhere in Bangladesh @endif</p>
        <p class="hidden sm:block"><i class="fa-solid fa-phone mr-1.5 text-accent-400"></i>Hotline {{ setting('store_phone') }}@if (setting('store_hours')) · {{ setting('store_hours') }}@endif</p>
    </div>
</div>

<header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6 lg:px-8">
        <a href="{{ $shopHome }}" class="flex shrink-0 items-center gap-2">
            <x-application-logo class="h-9 w-9" />
            <x-store-name class="hidden text-xl font-extrabold tracking-tight text-slate-900 sm:block" />
        </a>

        @if ($user?->isCustomer())
            <form action="{{ route('customer.dashboard') }}" method="GET" class="relative hidden flex-1 md:block md:max-w-xl md:mx-auto">
                <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search groceries, gadgets, fashion…"
                       class="form-input rounded-full border-slate-200 bg-slate-100 pl-10 focus:bg-white">
                @if (request('category'))
                    <input type="hidden" name="category" value="{{ request('category') }}">
                @endif
            </form>
        @else
            <div class="flex-1"></div>
        @endif

        <nav class="ml-auto flex items-center gap-1 sm:gap-2">
            @auth
                @if ($user->isCustomer())
                    <a href="{{ route('customer.dashboard') }}" @class(['btn-ghost hidden sm:inline-flex', 'text-brand-600' => request()->routeIs('customer.dashboard', 'shop.*')])>
                        <i class="fa-solid fa-store"></i><span class="hidden lg:inline">Shop</span>
                    </a>
                    <a href="{{ route('orders.index') }}" @class(['btn-ghost hidden sm:inline-flex', 'text-brand-600' => request()->routeIs('orders.*')])>
                        <i class="fa-solid fa-truck"></i><span class="hidden lg:inline">My orders</span>
                    </a>
                    <a href="{{ route('cart.index') }}" @class(['btn-ghost relative', 'text-brand-600' => request()->routeIs('cart.*')]) aria-label="Cart">
                        <i class="fa-solid fa-cart-shopping text-base"></i><span class="hidden lg:inline">Cart</span>
                        @if ($cartCount > 0)
                            <span class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-600 px-1 text-[11px] font-bold text-white ring-2 ring-white">{{ $cartCount }}</span>
                        @endif
                    </a>
                @else
                    <a href="{{ route('dashboard') }}" class="btn-secondary"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
                @endif

                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button @click="open = !open" class="flex items-center gap-2 rounded-full p-1 pr-2 hover:bg-slate-100">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-xs font-bold text-white">{{ $user->initials }}</span>
                        <i class="fa-solid fa-chevron-down text-[10px] text-slate-400"></i>
                    </button>
                    <div x-cloak x-show="open" x-transition.origin.top.right
                         class="absolute right-0 mt-2 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lift">
                        <div class="border-b border-slate-100 px-4 py-3">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                        </div>
                        @if ($user->isCustomer())
                            <a href="{{ route('orders.index') }}" class="flex items-center gap-3 px-4 py-2 text-sm hover:bg-slate-50"><i class="fa-solid fa-truck w-4 text-slate-400"></i> My orders</a>
                        @endif
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 px-4 py-2 text-sm hover:bg-slate-50"><i class="fa-solid fa-user-gear w-4 text-slate-400"></i> Profile</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="flex w-full items-center gap-3 px-4 py-2 text-left text-sm text-rose-600 hover:bg-rose-50"><i class="fa-solid fa-arrow-right-from-bracket w-4"></i> Log out</button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="btn-ghost">Log in</a>
                <a href="{{ route('register') }}" class="btn-primary">Create account</a>
            @endauth
        </nav>
    </div>

    @if ($user?->isCustomer())
        {{-- Mobile search --}}
        <form action="{{ route('customer.dashboard') }}" method="GET" class="relative px-4 pb-3 md:hidden">
            <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-7 top-[1.15rem] -translate-y-1/2 text-slate-400"></i>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search products…" class="form-input rounded-full border-slate-200 bg-slate-100 pl-10">
        </form>
    @endif
</header>

<main class="flex-1">
    <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
        <x-flash />
    </div>
    {{ $slot }}
</main>

<footer class="mt-16 border-t border-slate-200 bg-white">
    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 lg:px-8">
        <div>
            <div class="flex items-center gap-2">
                <x-application-logo class="h-8 w-8" />
                <x-store-name class="text-lg font-extrabold text-slate-900" />
            </div>
            <p class="mt-3 text-sm text-slate-500">{{ setting('store_tagline') }}</p>
        </div>
        <div>
            <h3 class="text-sm font-bold text-slate-900">Shop</h3>
            <ul class="mt-3 space-y-2 text-sm text-slate-500">
                @foreach (['Food', 'Electronics', 'Clothing', 'Home & Kitchen'] as $cat)
                    <li><a class="hover:text-brand-600" href="{{ $user?->isCustomer() ? route('customer.dashboard', ['category' => $cat]) : route('login') }}">{{ $cat }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h3 class="text-sm font-bold text-slate-900">Help</h3>
            <ul class="mt-3 space-y-2 text-sm text-slate-500">
                <li><i class="fa-solid fa-truck-fast mr-2 w-4 text-slate-400"></i>Delivery in 1–3 days</li>
                <li><i class="fa-solid fa-money-bill-wave mr-2 w-4 text-slate-400"></i>Cash on delivery</li>
                <li><i class="fa-solid fa-rotate-left mr-2 w-4 text-slate-400"></i>{{ setting('return_window_days') }}-day easy returns</li>
            </ul>
        </div>
        <div>
            <h3 class="text-sm font-bold text-slate-900">Contact</h3>
            <ul class="mt-3 space-y-2 text-sm text-slate-500">
                <li><i class="fa-solid fa-location-dot mr-2 w-4 text-slate-400"></i>{{ setting('store_address') }}</li>
                <li><i class="fa-solid fa-phone mr-2 w-4 text-slate-400"></i>{{ setting('store_phone') }}</li>
                <li><i class="fa-solid fa-envelope mr-2 w-4 text-slate-400"></i>{{ setting('store_email') }}</li>
            </ul>
        </div>
    </div>
    <div class="border-t border-slate-100 py-4 text-center text-xs text-slate-400">&copy; {{ date('Y') }} {{ setting('store_name') }}. All rights reserved.</div>
</footer>
@stack('scripts')
</body>
</html>
