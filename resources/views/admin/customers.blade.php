<x-panel-layout title="Customers" :subtitle="number_format($customers->total()) . ' registered customers, ranked by lifetime spend'">
    <div class="card overflow-hidden">
        <form method="GET" class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
            <div class="relative min-w-[16rem] flex-1">
                <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="search" name="q" value="{{ $search }}" placeholder="Search by name or email" class="form-input pl-9">
            </div>
            <button class="btn-dark">Search</button>
            @if ($search)
                <a href="{{ route('admin.customers') }}" class="btn-ghost">Clear</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Customer</th><th>Joined</th><th class="!text-right">Orders</th><th class="!text-right">Total spent</th><th>Last order</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($customers as $customer)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600">{{ $customer->initials }}</span>
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ $customer->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $customer->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="text-slate-500">{{ $customer->created_at->format('d M Y') }}</td>
                            <td class="text-right">
                                @if ($customer->orders_count)
                                    <a href="{{ route('manage.orders.index', ['q' => $customer->email]) }}" class="font-semibold text-brand-600 hover:underline">{{ $customer->orders_count }}</a>
                                @else
                                    <span class="text-slate-400">0</span>
                                @endif
                            </td>
                            <td class="text-right font-semibold">{{ money($customer->total_spent ?? 0) }}</td>
                            <td class="text-slate-500">{{ $customer->last_order_at ? \Carbon\Carbon::parse($customer->last_order_at)->diffForHumans() : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state icon="fa-users" title="No customers found" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($customers->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $customers->links() }}</div>
        @endif
    </div>
</x-panel-layout>
