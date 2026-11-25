<x-panel-layout title="Barcode labels" subtitle="Print shelf and product labels on A4 sticker sheets (3 × 8, 63.5 × 33.9 mm)">
    <form method="POST" action="{{ route('barcodes.print') }}" x-data="labelPicker()" class="grid gap-6 xl:grid-cols-3">
        @csrf
        <div class="space-y-4 xl:col-span-2">
            <div class="card p-4">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input x-model="q" @input.debounce.250ms="search()" @keydown.enter.prevent="scan()" type="text" autocomplete="off" autofocus
                           placeholder="Search products, or scan a barcode to add it" class="form-input py-2.5 pl-10">
                    <div x-show="results.length" x-cloak @click.outside="results = []" class="absolute inset-x-0 top-full z-30 mt-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lift">
                        <template x-for="p in results" :key="p.id">
                            <button type="button" @click="add(p)" class="flex w-full items-center gap-3 px-4 py-2 text-left hover:bg-slate-50">
                                <img :src="p.image_url" alt="" class="h-8 w-8 rounded bg-slate-50 object-contain">
                                <span class="flex-1 truncate text-sm font-semibold" x-text="p.name"></span>
                                <span class="font-mono text-xs text-slate-500" x-text="p.barcode"></span>
                            </button>
                        </template>
                    </div>
                </div>
                <p x-show="error" x-text="error" x-cloak class="form-error"></p>
            </div>

            <div class="card overflow-hidden">
                <table class="table">
                    <thead><tr><th>Product</th><th>Barcode</th><th class="!text-right">In stock</th><th class="w-32">Copies</th><th class="w-10"></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(item, i) in items" :key="item.id">
                            <tr>
                                <td><p class="font-semibold" x-text="item.name"></p><p class="text-xs text-slate-500" x-text="taka(item.price)"></p></td>
                                <td class="font-mono text-xs" x-text="item.barcode"></td>
                                <td class="text-right" x-text="item.stock"></td>
                                <td>
                                    <input type="hidden" :name="'items[' + i + '][product_id]'" :value="item.id">
                                    <input type="number" min="0" max="500" :name="'items[' + i + '][copies]'" x-model.number="item.copies" class="form-input py-1.5">
                                </td>
                                <td><button type="button" class="text-slate-300 hover:text-rose-600" @click="items.splice(i, 1)"><i class="fa-solid fa-xmark"></i></button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div x-show="! items.length" class="px-6 py-12 text-center text-sm text-slate-400">
                    <i class="fa-solid fa-tags mb-2 block text-3xl text-slate-300"></i>Add products to print labels for.
                </div>
            </div>
        </div>

        <div class="card h-fit space-y-4 p-5">
            <h2 class="card-title">Print options</h2>
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="show_price" value="0">
                <input type="checkbox" name="show_price" value="1" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Show price on labels
            </label>
            <div>
                <label class="form-label" for="skip">Skip positions on a used sheet</label>
                <input id="skip" type="number" name="skip" min="0" max="23" x-model.number="skip" class="form-input">
                <p class="mt-1 text-xs text-slate-500">Start printing after this many labels, to reuse a partly used sheet.</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-3 text-sm">
                <span class="font-semibold" x-text="total()"></span> labels on <span class="font-semibold" x-text="Math.ceil((total() + (skip || 0)) / 24)"></span> sheet(s)
            </div>
            <button class="btn-primary w-full" :disabled="! total()"><i class="fa-solid fa-file-pdf"></i> Download labels PDF</button>
            <p class="text-xs text-slate-500">Print at 100% scale (no "fit to page") so labels line up with the stickers.</p>
        </div>
    </form>

    @push('scripts')
        <script>
            function labelPicker() {
                return {
                    q: '', results: [], error: '', skip: 0,
                    items: @js($selected->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'barcode' => $p->barcode, 'price' => (float) $p->price, 'stock' => $p->stock_quantity, 'copies' => max(1, min(24, $p->stock_quantity))])->values()),
                    taka: (v) => '৳' + Number(v).toLocaleString('en-IN'),
                    total() { return this.items.reduce((n, i) => n + (parseInt(i.copies, 10) || 0), 0); },
                    add(p) {
                        const existing = this.items.find(i => i.id === p.id);
                        if (existing) existing.copies++;
                        else this.items.push({ id: p.id, name: p.name, barcode: p.barcode, price: p.price, stock: p.stock, copies: Math.max(1, Math.min(24, p.stock)) });
                        this.results = []; this.q = '';
                    },
                    async search() {
                        this.error = '';
                        if (this.q.trim().length < 2) { this.results = []; return; }
                        const res = await fetch(@js(route('barcodes.lookup')) + '?q=' + encodeURIComponent(this.q.trim()), { headers: { Accept: 'application/json' } });
                        this.results = res.ok ? (await res.json()).products : [];
                    },
                    async scan() {
                        const code = this.q.trim(); if (! code) return;
                        const res = await fetch(@js(route('barcodes.lookup')) + '?code=' + encodeURIComponent(code), { headers: { Accept: 'application/json' } });
                        const data = await res.json();
                        if (res.ok) this.add(data.product); else this.error = data.message;
                    },
                };
            }
        </script>
    @endpush
</x-panel-layout>
