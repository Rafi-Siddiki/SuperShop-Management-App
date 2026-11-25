<x-panel-layout title="Dashboard" :subtitle="'Welcome back, ' . auth()->user()->name . ' — here\'s how the store is doing.'">
    <x-slot:actions>
        <a href="{{ route('admin.sales-reports') }}" class="btn-secondary"><i class="fa-solid fa-chart-line"></i> Reports</a>
        <a href="{{ route('products.create') }}" class="btn-primary"><i class="fa-solid fa-plus"></i> Add product</a>
    </x-slot:actions>

    {{-- KPI row --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Revenue this month" :value="money($stats['revenue'])" icon="fa-sack-dollar" tone="brand"
                     :change="$stats['revenue_change']" hint="vs. same days last month" />
        <x-stat-card label="Orders this month" :value="number_format($stats['orders'])" icon="fa-receipt" tone="sky"
                     :change="$stats['orders_change']" hint="vs. same days last month" :href="route('manage.orders.index')" />
        <x-stat-card label="Gross profit this month" :value="money($stats['profit'])" icon="fa-chart-pie" tone="emerald"
                     :hint="$stats['margin'] !== null ? $stats['margin'] . '% margin on goods' : 'Add cost prices to see profit'" />
        <x-stat-card label="Stock value at cost" :value="money($stats['stock']['cost'])" icon="fa-boxes-stacked" tone="violet"
                     :hint="number_format($stats['stock']['units']) . ' units · ' . money($stats['stock']['retail']) . ' retail'" :href="route('inventory.index')" />
    </div>

    {{-- Action strip --}}
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('manage.orders.index', ['status' => 'pending']) }}" class="card flex items-center gap-4 p-4 transition hover:shadow-lift">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 text-amber-600"><i class="fa-solid fa-hourglass-half"></i></span>
            <div><p class="text-xl font-extrabold">{{ $stats['pending'] }}</p><p class="text-xs text-slate-500">Pending orders to confirm</p></div>
        </a>
        <a href="{{ route('manage.orders.index', ['status' => 'processing']) }}" class="card flex items-center gap-4 p-4 transition hover:shadow-lift">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-sky-100 text-sky-600"><i class="fa-solid fa-box-open"></i></span>
            <div><p class="text-xl font-extrabold">{{ $stats['processing'] }}</p><p class="text-xs text-slate-500">Orders being packed</p></div>
        </a>
        <a href="{{ route('inventory.index', ['filter' => 'low']) }}" class="card flex items-center gap-4 p-4 transition hover:shadow-lift">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-orange-100 text-orange-600"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <div><p class="text-xl font-extrabold">{{ $stats['stock']['low'] }}</p><p class="text-xs text-slate-500">At or below reorder level</p></div>
        </a>
        <a href="{{ route('inventory.index', ['filter' => 'out']) }}" class="card flex items-center gap-4 p-4 transition hover:shadow-lift">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-rose-100 text-rose-600"><i class="fa-solid fa-ban"></i></span>
            <div><p class="text-xl font-extrabold">{{ $stats['stock']['out'] }}</p><p class="text-xs text-slate-500">Out of stock of {{ $stats['products'] }} products</p></div>
        </a>
    </div>

    {{-- Today --}}
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        @foreach (['online' => ['Online orders today', 'fa-globe', 'bg-sky-100 text-sky-600'], 'pos' => ['In-store sales today', 'fa-cash-register', 'bg-indigo-100 text-indigo-600']] as $channel => [$label, $icon, $tone])
            @php($today = $stats['today_' . $channel])
            <a href="{{ route('manage.orders.index', ['channel' => $channel]) }}" class="card flex items-center gap-4 p-4 transition hover:shadow-lift">
                <span class="flex h-10 w-10 items-center justify-center rounded-full {{ $tone }}"><i class="fa-solid {{ $icon }}"></i></span>
                <div class="flex-1"><p class="text-xs text-slate-500">{{ $label }}</p><p class="text-xl font-extrabold">{{ money($today->revenue ?? 0) }}</p></div>
                <span class="text-sm font-semibold text-slate-500">{{ $today->orders ?? 0 }} {{ Str::plural('order', $today->orders ?? 0) }}</span>
            </a>
        @endforeach
    </div>

    {{-- Charts --}}
    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card flex flex-col xl:col-span-2">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Sales, last 30 days</h2>
                    <p class="text-xs text-slate-500">{{ money(array_sum($daily['revenue'])) }} from {{ number_format(array_sum($daily['orders'])) }} orders (cancelled & returned excluded)</p>
                </div>
                <a href="{{ route('admin.sales-reports') }}" class="btn-ghost btn-sm">Details <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="relative min-h-72 flex-1 p-4"><canvas id="revenueChart"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Sales by category</h2>
                    <p class="text-xs text-slate-500">Last 90 days</p>
                </div>
            </div>
            <div class="p-4">
                @if ($categorySales->isEmpty())
                    <x-empty-state icon="fa-chart-pie" title="No sales yet" />
                @else
                    <div class="mx-auto h-52 max-w-[13rem]"><canvas id="categoryChart"></canvas></div>
                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($categorySales->take(5) as $i => $row)
                            <li class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" data-swatch="{{ $i }}"></span>
                                <span class="flex-1 text-slate-600">{{ $row->category }}</span>
                                <span class="font-semibold">{{ money($row->revenue) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        {{-- Recent orders --}}
        <div class="card xl:col-span-2">
            <div class="card-header">
                <h2 class="card-title">Recent orders</h2>
                <a href="{{ route('manage.orders.index') }}" class="btn-ghost btn-sm">View all <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Status</th><th class="!text-right">Total</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($recentOrders as $order)
                            <tr class="cursor-pointer" onclick="window.location='{{ route('manage.orders.show', $order) }}'">
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $order->number }}</p>
                                    <p class="text-xs text-slate-500">{{ $order->created_at->diffForHumans() }}</p>
                                </td>
                                <td>{{ $order->customer_label }}</td>
                                <td>{{ $order->items_count }}</td>
                                <td><x-status-badge :status="$order->status" /></td>
                                <td class="text-right font-semibold">{{ money($order->total_amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty-state icon="fa-receipt" title="No orders yet" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            {{-- Best sellers --}}
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Best sellers</h2>
                    <a href="{{ route('products.top') }}" class="btn-ghost btn-sm">All <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($topProducts as $i => $product)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <span class="w-4 text-sm font-bold text-slate-400">{{ $i + 1 }}</span>
                            <img src="{{ \App\Models\Product::imageUrlFor($product->image) }}" alt="" class="h-10 w-10 rounded-lg bg-slate-50 object-contain p-0.5">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $product->name }}</p>
                                <p class="text-xs text-slate-500">{{ number_format($product->total_quantity) }} sold</p>
                            </div>
                            <span class="text-sm font-semibold">{{ money($product->total_revenue) }}</span>
                        </li>
                    @empty
                        <li><x-empty-state icon="fa-trophy" title="No sales yet" /></li>
                    @endforelse
                </ul>
            </div>

            {{-- Low stock --}}
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Needs restocking</h2>
                    <a href="{{ route('inventory.index', ['filter' => 'reorder']) }}" class="btn-ghost btn-sm">Restock <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($lowStock as $product)
                        <li class="flex items-center gap-3 px-5 py-2.5">
                            <img src="{{ $product->image_url }}" alt="" class="h-9 w-9 rounded-lg bg-slate-50 object-contain p-0.5">
                            <p class="min-w-0 flex-1 truncate text-sm">{{ $product->name }}</p>
                            <span @class(['badge', 'bg-rose-50 text-rose-700 ring-rose-600/20' => $product->stock_quantity <= 0, 'bg-amber-50 text-amber-700 ring-amber-600/20' => $product->stock_quantity > 0])>
                                {{ $product->stock_quantity <= 0 ? 'Out' : $product->stock_quantity . ' left' }}
                            </span>
                        </li>
                    @empty
                        <li><x-empty-state icon="fa-circle-check" title="Stock levels look healthy" /></li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            const palette = ['#e51d1d', '#ff8938', '#6366f1', '#10b981', '#0ea5e9', '#f59e0b', '#a855f7', '#64748b'];
            Chart.defaults.font.family = 'Manrope, sans-serif';
            Chart.defaults.color = '#64748b';

            const daily = @json($daily);
            const ctx = document.getElementById('revenueChart').getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, 260);
            gradient.addColorStop(0, 'rgba(229, 29, 29, 0.25)');
            gradient.addColorStop(1, 'rgba(229, 29, 29, 0)');

            new Chart(ctx, {
                data: {
                    labels: daily.labels,
                    datasets: [
                        { type: 'line', label: 'Revenue (৳)', data: daily.revenue, borderColor: '#e51d1d', backgroundColor: gradient, fill: true, tension: .35, pointRadius: 0, pointHoverRadius: 5, borderWidth: 2.5, yAxisID: 'y' },
                        { type: 'bar', label: 'Orders', data: daily.orders, backgroundColor: 'rgba(255, 137, 56, .35)', borderRadius: 4, yAxisID: 'y1', maxBarThickness: 14 },
                    ],
                },
                options: {
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                        tooltip: { callbacks: { label: (c) => c.dataset.yAxisID === 'y' ? ' ৳' + Math.round(c.parsed.y).toLocaleString() : ' ' + c.parsed.y + ' orders' } },
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } },
                        y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { callback: (v) => '৳' + (v >= 1000 ? (v / 1000) + 'k' : v) } },
                        y1: { beginAtZero: true, position: 'right', grid: { display: false }, ticks: { precision: 0 } },
                    },
                },
            });

            const categories = @json($categorySales);
            if (categories.length) {
                new Chart(document.getElementById('categoryChart'), {
                    type: 'doughnut',
                    data: {
                        labels: categories.map(c => c.category),
                        datasets: [{ data: categories.map(c => Number(c.revenue)), backgroundColor: palette, borderWidth: 2, borderColor: '#fff' }],
                    },
                    options: { cutout: '68%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ' ৳' + Math.round(c.parsed).toLocaleString() } } } },
                });
                document.querySelectorAll('[data-swatch]').forEach(el => el.style.background = palette[el.dataset.swatch]);
            }
        </script>
    @endpush
</x-panel-layout>
