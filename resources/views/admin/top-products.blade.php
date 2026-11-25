<x-panel-layout title="Top products" subtitle="All-time best sellers by units sold (cancelled orders excluded)">
    <x-slot:actions>
        <a href="{{ route('admin.sales-reports') }}" class="btn-secondary"><i class="fa-solid fa-arrow-left"></i> Reports</a>
        <a href="{{ route('products.top', ['download' => 'true']) }}" class="btn-primary"><i class="fa-solid fa-file-pdf"></i> Download PDF</a>
    </x-slot:actions>

    @php($maxUnits = max(1, (int) ($topProducts->max('total_quantity') ?? 1)))

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th class="w-12">#</th><th>Product</th><th>Category</th><th class="w-1/3">Units sold</th><th class="!text-right">Revenue</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($topProducts as $i => $product)
                        <tr>
                            <td>
                                @if ($i < 3)
                                    <span @class(['flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold text-white',
                                        'bg-amber-400' => $i === 0, 'bg-slate-400' => $i === 1, 'bg-orange-700/70' => $i === 2])>{{ $i + 1 }}</span>
                                @else
                                    <span class="pl-2 font-semibold text-slate-400">{{ $i + 1 }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <img src="{{ \App\Models\Product::imageUrlFor($product->image) }}" alt="" class="h-10 w-10 rounded-lg bg-slate-50 object-contain p-0.5">
                                    <span class="font-semibold text-slate-900">{{ $product->name }}</span>
                                </div>
                            </td>
                            <td class="text-slate-500">{{ $product->category }}</td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-accent-500" style="width: {{ $product->total_quantity / $maxUnits * 100 }}%"></div>
                                    </div>
                                    <span class="w-12 text-right font-semibold">{{ number_format($product->total_quantity) }}</span>
                                </div>
                            </td>
                            <td class="text-right font-semibold">{{ money($product->total_revenue) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state icon="fa-trophy" title="No sales yet" message="Best sellers will appear here once customers place orders." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-panel-layout>
