@props(['title' => null, 'subtitle' => null])

@php
    $user = auth()->user();

    $till = [
        ['Point of sale', 'pos.index', 'fa-cash-register', ['pos.*']],
        ['Scan barcode', 'barcodes.scan', 'fa-barcode', ['barcodes.scan']],
    ];

    $sections = $user->isAdmin()
        ? [
            'Overview' => [
                ['Dashboard', 'admin.dashboard', 'fa-gauge-high', ['admin.dashboard']],
                ['Sales & Reports', 'admin.sales-reports', 'fa-chart-line', ['admin.sales-reports', 'products.top']],
            ],
            'Counter' => $till,
            'Store' => [
                ['Orders', 'manage.orders.index', 'fa-receipt', ['manage.orders.*']],
                ['Products', 'products.index', 'fa-box', ['products.index', 'products.create', 'products.edit', 'products.import']],
                ['Coupons', 'admin.coupons', 'fa-ticket', ['admin.coupons*']],
            ],
            'Inventory' => [
                ['Stock levels', 'inventory.index', 'fa-boxes-stacked', ['inventory.index']],
                ['Stock movements', 'inventory.movements', 'fa-right-left', ['inventory.movements']],
                ['Barcode labels', 'barcodes.labels', 'fa-tags', ['barcodes.labels']],
            ],
            'People' => [
                ['Customers', 'admin.customers', 'fa-users', ['admin.customers']],
                ['Staff', 'staff.create', 'fa-user-shield', ['staff.*']],
            ],
            'System' => [
                ['Settings', 'admin.settings', 'fa-sliders', ['admin.settings']],
            ],
        ]
        : [
            'Work' => [
                ['Dashboard', 'employee.dashboard', 'fa-gauge-high', ['employee.dashboard']],
                ['Orders', 'manage.orders.index', 'fa-receipt', ['manage.orders.*']],
            ],
            'Counter' => $till,
            'Inventory' => [
                ['Stock levels', 'inventory.index', 'fa-boxes-stacked', ['inventory.index']],
                ['Stock movements', 'inventory.movements', 'fa-right-left', ['inventory.movements']],
                ['Barcode labels', 'barcodes.labels', 'fa-tags', ['barcodes.labels']],
            ],
        ];

    $pendingCount = \App\Models\Order::where('status', 'pending')->count();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
<head>
    @include('partials.head', ['title' => $title])
    @stack('head')
</head>
<body class="h-full font-sans text-slate-800 antialiased" x-data="{ sidebar: false }">
<div class="min-h-full">
    {{-- Mobile backdrop --}}
    <div x-cloak x-show="sidebar" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/60 lg:hidden" @click="sidebar = false"></div>

    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-slate-900 transition-transform duration-200 lg:translate-x-0"
           :class="{ 'translate-x-0': sidebar, '-translate-x-full': !sidebar }">
        <div class="flex h-16 shrink-0 items-center gap-3 px-5">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
                <x-application-logo class="h-9 w-9" />
                <x-store-name class="text-lg font-extrabold tracking-tight text-white" accent="text-accent-400" />
            </a>
            <button class="ml-auto text-slate-400 hover:text-white lg:hidden" @click="sidebar = false" aria-label="Close menu">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
            @foreach ($sections as $heading => $links)
                <div>
                    <p class="px-3 pb-2 text-[11px] font-bold uppercase tracking-widest text-slate-500">{{ $heading }}</p>
                    <div class="space-y-1">
                        @foreach ($links as [$label, $route, $icon, $patterns])
                            @php($active = request()->routeIs(...$patterns))
                            <a href="{{ route($route) }}" @class(['nav-item', 'nav-item-active' => $active])>
                                <i class="fa-solid {{ $icon }} w-5 text-center"></i>
                                <span>{{ $label }}</span>
                                @if ($route === 'manage.orders.index' && $pendingCount > 0)
                                    <span class="ml-auto rounded-full bg-amber-400 px-2 py-0.5 text-[11px] font-bold text-slate-900">{{ $pendingCount }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <div class="border-t border-white/10 p-3">
            <div class="flex items-center gap-3 rounded-lg px-2 py-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-sm font-bold text-white">{{ $user->initials }}</span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-white">{{ $user->name }}</p>
                    <p class="truncate text-xs capitalize text-slate-400">{{ $user->role }}</p>
                </div>
            </div>
            <div class="mt-1 grid grid-cols-2 gap-1">
                <a href="{{ route('profile.edit') }}" @class(['nav-item justify-center', 'nav-item-active' => request()->routeIs('profile.*')])>
                    <i class="fa-solid fa-user-gear"></i> Profile
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-item w-full justify-center"><i class="fa-solid fa-arrow-right-from-bracket"></i> Log out</button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Main column --}}
    <div class="lg:pl-64">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
            <div class="flex min-h-16 flex-wrap items-center gap-x-4 gap-y-3 px-4 py-3 sm:px-6 lg:px-8">
                <button class="-ml-1 rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden" @click="sidebar = true" aria-label="Open menu">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div class="min-w-0 flex-1">
                    @if ($title)
                        <h1 class="truncate text-xl font-extrabold tracking-tight text-slate-900">{{ $title }}</h1>
                    @endif
                    @if ($subtitle)
                        <p class="truncate text-sm text-slate-500">{{ $subtitle }}</p>
                    @endif
                </div>
                @isset($actions)
                    <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
                @endisset
            </div>
        </header>

        <main class="px-4 py-6 sm:px-6 lg:px-8">
            <x-flash />
            {{ $slot }}
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
