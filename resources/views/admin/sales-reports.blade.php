<x-panel-layout title="Sales & Reports" subtitle="Trends, category performance and downloadable PDF reports">
    <x-slot:actions>
        <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5 shadow-sm">
            @foreach ([7, 30, 90] as $range)
                <a href="{{ route('admin.sales-reports', ['days' => $range]) }}"
                   @class(['rounded-md px-3 py-1.5 text-xs font-semibold transition', 'bg-slate-900 text-white' => $days === $range, 'text-slate-600 hover:bg-slate-100' => $days !== $range])>
                    {{ $range }} days
                </a>
            @endforeach
        </div>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card :label="'Revenue, last ' . $days . ' days'" :value="money($totalSales)" icon="fa-sack-dollar" />
        <x-stat-card :label="'Orders, last ' . $days . ' days'" :value="number_format($totalOrders)" icon="fa-receipt" tone="sky" />
        <x-stat-card label="Gross profit" :value="money($profit['profit'])" icon="fa-chart-pie" tone="emerald"
                     :hint="($profit['margin'] !== null ? $profit['margin'] . '% margin · ' : '') . 'avg. order ' . money($totalOrders ? $totalSales / $totalOrders : 0)" />
        @php
            $placed = $statusBreakdown->sum();
            $cancelled = $statusBreakdown['cancelled'] ?? 0;
        @endphp
        <x-stat-card label="Cancellation rate" :value="($placed ? round($cancelled / $placed * 100, 1) : 0) . '%'"
                     icon="fa-ban" tone="rose" :hint="$cancelled . ' of ' . $placed . ' orders placed'" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card flex flex-col xl:col-span-2">
            <div class="card-header"><h2 class="card-title">Daily revenue and orders</h2></div>
            <div class="relative min-h-80 flex-1 p-4"><canvas id="dailyChart"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Order status mix</h2></div>
            <div class="p-5">
                @php($statusTotal = max(1, $statusBreakdown->sum()))
                <div class="space-y-4">
                    @foreach (\App\Models\Order::STATUSES as $status)
                        @php($count = $statusBreakdown[$status] ?? 0)
                        <div>
                            <div class="mb-1.5 flex items-center justify-between text-sm">
                                <x-status-badge :status="$status" />
                                <span class="font-semibold">{{ $count }} <span class="font-normal text-slate-400">· {{ round($count / $statusTotal * 100) }}%</span></span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div @class([
                                    'h-full rounded-full',
                                    'bg-amber-400' => $status === 'pending',
                                    'bg-sky-500' => $status === 'processing',
                                    'bg-indigo-500' => $status === 'shipped',
                                    'bg-emerald-500' => $status === 'delivered',
                                    'bg-rose-500' => $status === 'cancelled',
                                    'bg-orange-500' => $status === 'returned',
                                ]) style="width: {{ $count / $statusTotal * 100 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card flex flex-col xl:col-span-2">
            <div class="card-header"><h2 class="card-title">Monthly revenue, last 12 months</h2></div>
            <div class="relative min-h-72 flex-1 p-4"><canvas id="monthlyChart"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Category performance</h2><span class="text-xs text-slate-500">last {{ $days }} days</span></div>
            <ul class="divide-y divide-slate-100">
                @php($maxCat = max(1, (float) ($categorySales->max('revenue') ?? 1)))
                @forelse ($categorySales as $row)
                    <li class="px-5 py-3">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-semibold text-slate-700">{{ $row->category }}</span>
                            <span class="font-bold">{{ money($row->revenue) }}</span>
                        </div>
                        <div class="mt-1.5 flex items-center gap-3">
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-accent-500" style="width: {{ $row->revenue / $maxCat * 100 }}%"></div>
                            </div>
                            <span class="w-16 text-right text-xs text-slate-500">{{ number_format($row->units) }} units</span>
                        </div>
                    </li>
                @empty
                    <li><x-empty-state icon="fa-chart-pie" title="No sales in this period" /></li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- Channels and payment methods --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Online vs in-store</h2><span class="text-xs text-slate-500">last {{ $days }} days</span></div>
            <div class="grid grid-cols-2 divide-x divide-slate-100">
                @foreach (\App\Models\Order::CHANNELS as $key => [$label, $icon])
                    @php($row = $channels->get($key))
                    <div class="p-5">
                        <p class="text-sm text-slate-500"><i class="fa-solid {{ $icon }} mr-1"></i>{{ $label }}</p>
                        <p class="mt-1 text-2xl font-extrabold">{{ money($row->revenue ?? 0) }}</p>
                        <p class="text-xs text-slate-500">{{ number_format($row->orders ?? 0) }} orders · {{ $totalSales > 0 ? round(($row->revenue ?? 0) / $totalSales * 100) : 0 }}% of sales</p>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Payment methods</h2><span class="text-xs text-slate-500">last {{ $days }} days</span></div>
            <ul class="divide-y divide-slate-100">
                @forelse ($payments as $row)
                    <li class="flex items-center gap-3 px-5 py-2.5 text-sm">
                        <span class="flex-1 font-semibold text-slate-700">{{ \App\Models\Order::PAYMENT_METHODS[$row->payment_method] ?? $row->payment_method }}</span>
                        <span class="text-slate-500">{{ number_format($row->orders) }} orders</span>
                        <span class="w-28 text-right font-bold">{{ money($row->revenue) }}</span>
                    </li>
                @empty
                    <li><x-empty-state icon="fa-money-bill-wave" title="No sales in this period" /></li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- Downloads --}}
    <h2 class="mb-3 mt-8 text-lg font-extrabold text-slate-900">Download reports</h2>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="card flex flex-col p-5">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><i class="fa-solid fa-trophy"></i></span>
            <h3 class="mt-3 font-bold">Top products</h3>
            <p class="mt-1 flex-1 text-sm text-slate-500">All-time ranking of products by units sold.</p>
            <div class="mt-4 flex gap-2">
                <a href="{{ route('products.top') }}" class="btn-secondary btn-sm flex-1">View</a>
                <a href="{{ route('products.top', ['download' => 'true']) }}" class="btn-dark btn-sm flex-1"><i class="fa-solid fa-file-pdf"></i> PDF</a>
            </div>
        </div>

        <form action="{{ route('downloadSeasonalReport') }}" method="GET" class="card flex flex-col p-5">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><i class="fa-solid fa-cloud-sun-rain"></i></span>
            <h3 class="mt-3 font-bold">Seasonal report</h3>
            <p class="mt-1 text-sm text-slate-500">Best sellers for summer, rainy season or winter.</p>
            <div class="mt-4 grid flex-1 grid-cols-2 items-end gap-2">
                <select name="season" required class="form-input py-1.5">
                    <option value="summer">Summer</option>
                    <option value="rainy">Rainy</option>
                    <option value="winter">Winter</option>
                </select>
                <select name="year" required class="form-input py-1.5">
                    @for ($y = now()->year; $y >= 2020; $y--)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <button class="btn-dark btn-sm mt-2"><i class="fa-solid fa-file-pdf"></i> Download</button>
        </form>

        <form action="{{ route('monthly.report.pdf') }}" method="GET" class="card flex flex-col p-5">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 text-sky-600"><i class="fa-solid fa-calendar-days"></i></span>
            <h3 class="mt-3 font-bold">Monthly report</h3>
            <p class="mt-1 text-sm text-slate-500">Units and revenue per product for one month.</p>
            <div class="mt-4 grid flex-1 grid-cols-2 items-end gap-2">
                <select name="month" required class="form-input py-1.5">
                    @foreach (range(1, 12) as $m)
                        <option value="{{ $m }}" @selected($m === now()->month)>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                    @endforeach
                </select>
                <select name="year" required class="form-input py-1.5">
                    @for ($y = now()->year; $y >= 2020; $y--)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <button class="btn-dark btn-sm mt-2"><i class="fa-solid fa-file-pdf"></i> Download</button>
        </form>

        <div class="card flex flex-col p-5">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600"><i class="fa-solid fa-boxes-stacked"></i></span>
            <h3 class="mt-3 font-bold">Stock report</h3>
            <p class="mt-1 flex-1 text-sm text-slate-500">Current stock level of every product.</p>
            <a href="{{ route('admin.stock.report') }}" class="btn-dark btn-sm mt-4"><i class="fa-solid fa-file-pdf"></i> Download</a>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            Chart.defaults.font.family = 'Manrope, sans-serif';
            Chart.defaults.color = '#64748b';
            const taka = (v) => '৳' + (v >= 1000000 ? (v / 1000000).toFixed(1) + 'M' : v >= 1000 ? Math.round(v / 1000) + 'k' : v);

            const daily = @json($daily);
            new Chart(document.getElementById('dailyChart'), {
                data: {
                    labels: daily.labels,
                    datasets: [
                        { type: 'bar', label: 'Revenue (৳)', data: daily.revenue, backgroundColor: '#e51d1d', borderRadius: 4, maxBarThickness: 22, yAxisID: 'y' },
                        { type: 'line', label: 'Orders', data: daily.orders, borderColor: '#ff8938', backgroundColor: '#ff8938', tension: .35, pointRadius: 2, yAxisID: 'y1' },
                    ],
                },
                options: {
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { maxTicksLimit: 12 } },
                        y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { callback: taka } },
                        y1: { beginAtZero: true, position: 'right', grid: { display: false }, ticks: { precision: 0 } },
                    },
                },
            });

            const monthly = @json($monthly);
            new Chart(document.getElementById('monthlyChart'), {
                type: 'bar',
                data: {
                    labels: monthly.labels,
                    datasets: [{ label: 'Revenue (৳)', data: monthly.revenue, backgroundColor: (c) => c.dataIndex === monthly.labels.length - 1 ? '#ff8938' : '#0f172a', borderRadius: 6, maxBarThickness: 36 }],
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ' ৳' + Math.round(c.parsed.y).toLocaleString() + ' · ' + monthly.orders[c.dataIndex] + ' orders' } } },
                    scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { callback: taka } } },
                },
            });
        </script>
    @endpush
</x-panel-layout>
