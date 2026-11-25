<x-panel-layout title="Products" :subtitle="number_format($products->total()) . ' products in the catalogue'">
    <x-slot:actions>
        <a href="{{ route('products.import') }}" class="btn-secondary"><i class="fa-solid fa-file-csv"></i> Import / export</a>
        <a href="{{ route('products.create') }}" class="btn-primary"><i class="fa-solid fa-plus"></i> Add product</a>
    </x-slot:actions>

    <div class="card overflow-hidden" x-data="{ selected: [] }">
        <form method="GET" class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
            <div class="relative min-w-[14rem] flex-1">
                <i class="fa-solid fa-barcode pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="search" name="q" value="{{ $search }}" placeholder="Name, SKU or scan a barcode" class="form-input pl-9">
            </div>
            <select name="category" class="form-input w-auto" onchange="this.form.submit()">
                <option value="">All categories</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat }}" @selected($category === $cat)>{{ $cat }}</option>
                @endforeach
            </select>
            <select name="status" class="form-input w-auto" onchange="this.form.submit()">
                <option value="">Active & inactive</option>
                <option value="active" @selected($status === 'active')>Active only</option>
                <option value="inactive" @selected($status === 'inactive')>Inactive only</option>
            </select>
            <button class="btn-dark">Search</button>
            @if ($search || $category || $status)
                <a href="{{ route('products.index') }}" class="btn-ghost">Clear</a>
            @endif
        </form>

        <div x-show="selected.length" x-cloak class="flex items-center gap-3 border-b border-slate-100 bg-brand-50/60 px-4 py-2 text-sm">
            <span class="font-semibold text-brand-800" x-text="selected.length + ' selected'"></span>
            <a :href="@js(route('barcodes.labels')) + '?' + selected.map(id => 'ids[]=' + id).join('&')" class="btn-secondary btn-sm"><i class="fa-solid fa-tags"></i> Print labels</a>
            <button type="button" class="btn-ghost btn-sm" @click="selected = []">Clear selection</button>
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th class="w-8"></th><th>Product</th><th>Codes</th><th>Category</th><th class="!text-right">Price</th><th class="!text-right">Margin</th><th>Stock</th><th class="!text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($products as $product)
                        <tr @class(['bg-slate-50/60' => ! $product->is_active])>
                            <td><input type="checkbox" value="{{ $product->id }}" x-model="selected" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"></td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <img src="{{ $product->image_url }}" alt="" @class(['h-12 w-12 shrink-0 rounded-lg border border-slate-100 bg-slate-50 object-contain p-0.5', 'opacity-50 grayscale' => ! $product->is_active])>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-slate-900">{{ $product->name }}</p>
                                        @unless ($product->is_active)
                                            <span class="badge bg-slate-200 text-slate-600 ring-0">Inactive</span>
                                        @else
                                            <p class="max-w-xs truncate text-xs text-slate-500">{{ $product->description }}</p>
                                        @endunless
                                    </div>
                                </div>
                            </td>
                            <td class="font-mono text-xs text-slate-500">{{ $product->sku }}<br>{{ $product->barcode }}</td>
                            <td><span class="badge bg-slate-50 text-slate-600 ring-slate-500/20"><i class="fa-solid {{ $product->category_icon }} text-[10px]"></i>{{ $product->category }}</span></td>
                            <td class="text-right font-semibold">{{ money($product->price) }}</td>
                            <td @class(['text-right text-sm', 'text-rose-600' => ($product->margin_percent ?? 0) < 0, 'text-slate-600' => ($product->margin_percent ?? 0) >= 0])>
                                {{ $product->margin_percent !== null ? $product->margin_percent . '%' : '—' }}
                            </td>
                            <td>
                                @if ($product->is_out_of_stock)
                                    <span class="badge bg-rose-50 text-rose-700 ring-rose-600/20">Out</span>
                                @elseif ($product->is_low_stock)
                                    <span class="badge bg-amber-50 text-amber-700 ring-amber-600/20">{{ $product->stock_quantity }}</span>
                                @else
                                    <span class="text-slate-700">{{ number_format($product->stock_quantity) }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex justify-end gap-1">
                                    <a href="{{ route('products.edit', $product) }}" class="btn-ghost btn-sm" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                    <a href="{{ route('inventory.movements', ['product' => $product->id]) }}" class="btn-ghost btn-sm" title="Stock history"><i class="fa-solid fa-clock-rotate-left"></i></a>
                                    <form action="{{ route('products.toggle', $product) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button class="btn-ghost btn-sm" title="{{ $product->is_active ? 'Pause selling' : 'Resume selling' }}"><i class="fa-solid {{ $product->is_active ? 'fa-eye-slash' : 'fa-eye' }}"></i></button>
                                    </form>
                                    <form action="{{ route('products.destroy', $product) }}" method="POST" onsubmit="return confirm(@js('Remove ' . $product->name . ' from the catalogue? Past orders and stock history are kept.'))">
                                        @csrf @method('DELETE')
                                        <button class="btn-ghost btn-sm text-rose-600 hover:bg-rose-50 hover:text-rose-700" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">
                            <x-empty-state icon="fa-box-open" title="No products found" message="Try a different search, or add your first product.">
                                <a href="{{ route('products.create') }}" class="btn-primary"><i class="fa-solid fa-plus"></i> Add product</a>
                            </x-empty-state>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($products->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $products->links() }}</div>
        @endif
    </div>
</x-panel-layout>
