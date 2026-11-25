<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-white">
<head>
    @include('partials.head', ['title' => null])
</head>
<body class="h-full font-sans text-slate-800 antialiased">
<div class="flex min-h-full">
    {{-- Brand panel --}}
    <div class="relative hidden w-0 flex-1 overflow-hidden bg-slate-900 lg:block">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,_rgba(255,137,56,0.45),_transparent_55%),radial-gradient(ellipse_at_bottom_right,_rgba(229,29,29,0.55),_transparent_55%)]"></div>
        <div class="relative flex h-full flex-col justify-between p-12 text-white">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <x-application-logo class="h-11 w-11" />
                <x-store-name class="text-2xl font-extrabold" accent="text-accent-400" />
            </a>
            <div>
                <p class="text-4xl font-extrabold leading-tight">Groceries, gadgets<br>and everything between.</p>
                <p class="mt-4 max-w-md text-slate-300">Sign in to shop hundreds of products with cash on delivery, track your orders, and download invoices any time.</p>
            </div>
            <div class="flex gap-8 text-sm text-slate-300">
                <span><i class="fa-solid fa-truck-fast mr-2 text-accent-400"></i>1–3 day delivery</span>
                <span><i class="fa-solid fa-money-bill-wave mr-2 text-accent-400"></i>Cash on delivery</span>
                <span><i class="fa-solid fa-rotate-left mr-2 text-accent-400"></i>Easy returns</span>
            </div>
        </div>
    </div>

    {{-- Form panel --}}
    <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:flex-none lg:px-20 xl:px-24">
        <div class="mx-auto w-full max-w-sm">
            <a href="{{ route('home') }}" class="flex items-center gap-2 lg:hidden">
                <x-application-logo class="h-10 w-10" />
                <x-store-name class="text-xl font-extrabold" />
            </a>
            <div class="mt-8 lg:mt-0">
                <x-flash />
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
</body>
</html>
