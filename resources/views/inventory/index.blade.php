@php($isAdmin = auth()->user()->isAdmin())

<x-panel-layout title="Stock levels" subtitle="Receive deliveries, write off damaged goods and record stock counts — every change is logged">
    <x-slot:actions>
        <a href="{{ route('barcodes.scan') }}" class="btn-secondary"><i class="fa-solid fa-barcode"></i> Scan</a>
        <a href="{{ route('inventory.movements') }}" class="btn-secondary"><i class="fa-solid fa-right-left"></i> Movements</a>
        @if ($isAdmin)
            <a href="{{ route('inventory.export') }}" class="btn-secondary"><i class="fa-solid fa-file-csv"></i> Export</a>
            <a href="{{ route('admin.stock.report') }}" class="btn-secondary"><i class="fa-solid fa-file-pdf"></i> Report</a>
        @endif
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Units in stock" :value="number_format($summary['units'])" icon="fa-cubes" tone="sky" />
        @if ($isAdmin)
            <x-stat-card label="Stock value at cost" :value="money($summary['cost'])" icon="fa-coins" tone="emerald" :hint="money($summary['retail']) . ' at selling price'" />
        @else
            <x-stat-card label="Stock value (retail)" :value="money($summary['retail'])" icon="fa-coins" tone="emerald" />
        @endif
        <x-stat-card label="At or below reorder level" :value="$summary['low']" icon="fa-triangle-exclamation" tone="amber" :href="route('inventory.index', ['filter' => 'low'])" />
        <x-stat-card label="Out of stock" :value="$summary['out']" icon="fa-ban" tone="rose" :href="route('inventory.index', ['filter' => 'out'])" />
    </div>

    <div x-data="stockDialog()" class="mt-6">
        <div class="card overflow-hidden">
            <form method="GET" class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
                <div class="relative min-w-[14rem] flex-1">
                    <i class="fa-solid fa-barcode pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="search" name="q" value="{{ $search }}" placeholder="Name, SKU or scan a barcode" class="form-input pl-9" autofocus>
                </div>
                <select name="category" class="form-input w-auto" onchange="this.form.submit()">
                    <option value="">All categories</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat }}" @selected($category === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
                <select name="filter" class="form-input w-auto" onchange="this.form.submit()">
                    <option value="">All stock levels</option>
                    <option value="reorder" @selected($filter === 'reorder')>Needs reordering (incl. out of stock)</option>
                    <option value="low" @selected($filter === 'low')>Running low</option>
                    <option value="out" @selected($filter === 'out')>Out of stock</option>
                    <option value="inactive" @selected($filter === 'inactive')>Inactive products</option>
                </select>
                <button class="btn-dark">Search</button>
                @if ($search || $category || $filter)
                    <a href="{{ route('inventory.index') }}" class="btn-ghost">Clear</a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Product</th><th>Codes</th><th class="!text-right">Price</th><th class="!text-right">In stock</th><th class="!text-right">Reorder at</th><th class="!text-right">Actions</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($products as $product)
                            @php($qty = $product->stock_quantity)
                            <tr @class(['opacity-60' => ! $product->is_active])>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <img src="{{ $product->image_url }}" alt="" class="h-10 w-10 shrink-0 rounded-lg bg-slate-50 object-contain p-0.5">
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-slate-900">{{ $product->name }}</p>
                                            <p class="text-xs text-slate-500">{{ $product->category }}@unless ($product->is_active) · <span class="font-semibold text-slate-600">inactive</span>@endunless</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="font-mono text-xs text-slate-500">{{ $product->sku }}<br>{{ $product->barcode }}</td>
                                <td class="text-right">
                                    {{ money($product->price) }}
                                    @if ($isAdmin && $product->cost_price !== null)
                                        <p class="text-xs text-slate-400">cost {{ money($product->cost_price) }}</p>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if ($qty <= 0)
                                        <span class="badge bg-rose-50 text-rose-700 ring-rose-600/20">Out of stock</span>
                                    @elseif ($product->is_low_stock)
                                        <span class="badge bg-amber-50 text-amber-700 ring-amber-600/20">{{ $qty }} · low</span>
                                    @else
                                        <span class="font-semibold">{{ number_format($qty) }}</span>
                                    @endif
                                </td>
                                <td class="text-right text-slate-500">{{ $product->reorder_threshold }}</td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <button type="button" class="btn-primary btn-sm" @click="open('receive', @js(['id' => $product->id, 'name' => $product->name, 'qty' => $qty, 'cost' => $isAdmin ? $product->cost_price : null]))">
                                            <i class="fa-solid fa-truck-ramp-box"></i> Receive
                                        </button>
                                        @if ($isAdmin)
                                            <button type="button" class="btn-secondary btn-sm" title="Stock count" @click="open('count', @js(['id' => $product->id, 'name' => $product->name, 'qty' => $qty]))"><i class="fa-solid fa-clipboard-check"></i></button>
                                            <button type="button" class="btn-secondary btn-sm" title="Write off" @click="open('write-off', @js(['id' => $product->id, 'name' => $product->name, 'qty' => $qty]))" @disabled($qty <= 0)><i class="fa-solid fa-heart-crack"></i></button>
                                        @endif
                                        <a href="{{ route('inventory.movements', ['product' => $product->id]) }}" class="btn-ghost btn-sm" title="Stock history"><i class="fa-solid fa-clock-rotate-left"></i></a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-empty-state icon="fa-boxes-stacked" title="No products match" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($products->hasPages())
                <div class="border-t border-slate-100 px-4 py-3">{{ $products->links() }}</div>
            @endif
        </div>

        {{-- Receive / count / write-off dialog --}}
        <div x-cloak x-show="action" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" @keydown.escape.window="close()">
            <form method="POST" :action="url" class="card w-full max-w-md p-6" @click.outside="close()" @submit="submitting = true">
                @csrf
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-extrabold text-slate-900" x-text="{ receive: 'Receive delivery', count: 'Stock count', 'write-off': 'Write off stock' }[action]"></h3>
                        <p class="text-sm text-slate-500"><span x-text="product.name"></span> · <span x-text="product.qty"></span> in stock now</p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-700" @click="close()"><i class="fa-solid fa-xmark"></i></button>
                </div>

                <div class="mt-5 space-y-4">
                    <template x-if="action === 'receive'">
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="form-label">Quantity received</label>
                                    <input type="number" name="quantity" min="1" max="100000" required class="form-input" x-init="$nextTick(() => $el.focus())">
                                </div>
                                @if ($isAdmin)
                                    <div>
                                        <label class="form-label">Unit cost (৳)</label>
                                        <input type="number" name="unit_cost" min="0" step="0.01" class="form-input" :placeholder="product.cost ?? 'optional'">
                                    </div>
                                @endif
                            </div>
                            @if ($isAdmin)
                                <p class="text-xs text-slate-500">With a unit cost, the product's cost price becomes the weighted average of stock on hand and this delivery.</p>
                            @endif
                        </div>
                    </template>
                    <template x-if="action === 'count'">
                        <div>
                            <label class="form-label">Quantity counted on the shelf</label>
                            <input type="number" name="counted" min="0" max="1000000" required class="form-input" x-init="$el.value = product.qty; $nextTick(() => $el.select())">
                            <p class="mt-1.5 text-xs text-slate-500">The difference from the recorded quantity is logged as a stock-count adjustment.</p>
                        </div>
                    </template>
                    <template x-if="action === 'write-off'">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label">Quantity</label>
                                <input type="number" name="quantity" min="1" :max="product.qty" required class="form-input" x-init="$nextTick(() => $el.focus())">
                            </div>
                            <div>
                                <label class="form-label">Reason</label>
                                <select name="reason" required class="form-input">
                                    <option value="damaged">Damaged</option>
                                    <option value="expired">Expired</option>
                                    <option value="lost">Lost / theft</option>
                                </select>
                            </div>
                        </div>
                    </template>
                    <div>
                        <label class="form-label">Note</label>
                        <input type="text" name="note" maxlength="255" class="form-input" :placeholder="{ receive: 'Supplier, invoice no.…', count: 'e.g. monthly count', 'write-off': 'What happened?' }[action]">
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="btn-secondary" @click="close()">Cancel</button>
                    <button class="btn-primary" :disabled="submitting">
                        <i class="fa-solid fa-check"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            function stockDialog() {
                const urls = {
                    receive: @js(route('inventory.receive', '__ID__')),
                    count: @js($isAdmin ? route('inventory.count', '__ID__') : ''),
                    'write-off': @js($isAdmin ? route('inventory.write-off', '__ID__') : ''),
                };
                return {
                    action: null, product: {}, url: '', submitting: false,
                    open(action, product) { this.product = product; this.url = urls[action].replace('__ID__', product.id); this.action = action; this.submitting = false; },
                    close() { this.action = null; },
                };
            }
        </script>
    @endpush
</x-panel-layout>
