<x-panel-layout title="Stock movements" :subtitle="$product ? $product->name . ' · ' . $product->sku . ' · ' . $product->stock_quantity . ' in stock now' : 'Every change to stock: deliveries, sales, returns, write-offs and counts'">
    <x-slot:actions>
        <a href="{{ route('inventory.index') }}" class="btn-secondary"><i class="fa-solid fa-boxes-stacked"></i> Stock levels</a>
    </x-slot:actions>

    <div class="card overflow-hidden">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-100 p-4">
            @if ($product)<input type="hidden" name="product" value="{{ $product->id }}">@endif
            <div>
                <label class="form-label text-xs">Type</label>
                <select name="type" class="form-input w-auto py-1.5" onchange="this.form.submit()">
                    <option value="">All types</option>
                    @foreach (\App\Models\StockMovement::TYPES as $key => [$label])
                        <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label text-xs">From</label>
                <input type="date" name="from" value="{{ $from?->format('Y-m-d') }}" class="form-input py-1.5">
            </div>
            <div>
                <label class="form-label text-xs">To</label>
                <input type="date" name="to" value="{{ $to?->format('Y-m-d') }}" class="form-input py-1.5">
            </div>
            <button class="btn-dark">Filter</button>
            @if ($type || $from || $to || $product)
                <a href="{{ route('inventory.movements') }}" class="btn-ghost">Clear{{ $product ? ' (all products)' : '' }}</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>When</th>@unless ($product)<th>Product</th>@endunless<th>Type</th><th class="!text-right">Change</th><th class="!text-right">Balance</th><th>Reference</th><th>By</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($movements as $m)
                        <tr>
                            <td class="whitespace-nowrap">
                                <p>{{ $m->created_at->format('d M Y') }}</p>
                                <p class="text-xs text-slate-500">{{ $m->created_at->format('h:i A') }}</p>
                            </td>
                            @unless ($product)
                                <td>
                                    <a href="{{ route('inventory.movements', ['product' => $m->product_id]) }}" class="font-medium text-slate-800 hover:text-brand-600">{{ $m->product?->name ?? 'Deleted product' }}</a>
                                    <p class="font-mono text-xs text-slate-400">{{ $m->product?->sku }}</p>
                                </td>
                            @endunless
                            <td><span class="badge {{ $m->style }}"><i class="fa-solid {{ $m->icon }} text-[10px]"></i>{{ $m->label }}</span></td>
                            <td @class(['text-right font-bold tabular-nums', 'text-emerald-600' => $m->change > 0, 'text-rose-600' => $m->change < 0, 'text-slate-400' => $m->change === 0])>
                                {{ $m->change > 0 ? '+' : '' }}{{ number_format($m->change) }}
                            </td>
                            <td class="text-right tabular-nums">{{ number_format($m->balance_after) }}</td>
                            <td class="text-sm">
                                @if ($m->order)
                                    <a href="{{ route('manage.orders.show', $m->order) }}" class="font-semibold text-brand-600 hover:underline">{{ $m->order->number }}</a>
                                @endif
                                @if ($m->unit_cost !== null && auth()->user()->isAdmin())
                                    <span class="text-xs text-slate-500">@ {{ money($m->unit_cost) }}</span>
                                @endif
                                @if ($m->note)
                                    <p class="max-w-xs truncate text-xs text-slate-500" title="{{ $m->note }}">{{ $m->note }}</p>
                                @endif
                            </td>
                            <td class="text-sm text-slate-500">{{ $m->user?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="fa-right-left" title="No stock movements" message="Nothing matches these filters." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($movements->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $movements->links() }}</div>
        @endif
    </div>
</x-panel-layout>
