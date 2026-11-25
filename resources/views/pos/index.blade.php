<x-panel-layout title="Point of sale" :subtitle="'Your sales today: ' . $todaySales->count . ' · ' . money($todaySales->total)">
    <x-slot:actions>
        <span class="hidden text-xs text-slate-500 lg:inline"><kbd class="rounded border border-slate-300 bg-white px-1.5 py-0.5 font-mono">F2</kbd> scan · <kbd class="rounded border border-slate-300 bg-white px-1.5 py-0.5 font-mono">F9</kbd> complete sale · <kbd class="rounded border border-slate-300 bg-white px-1.5 py-0.5 font-mono">3*code</kbd> adds 3</span>
    </x-slot:actions>

    <div x-data="pos()" x-init="init()" @keydown.window.f2.prevent="focusScan()" @keydown.window.f9.prevent="complete()" class="grid gap-6 xl:grid-cols-3">
        {{-- Basket --}}
        <div class="space-y-4 xl:col-span-2">
            <div class="card p-4">
                <form @submit.prevent="scan()" class="flex gap-2">
                    <div class="relative flex-1">
                        <i class="fa-solid fa-barcode pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-lg text-slate-400"></i>
                        <input x-ref="scan" x-model="input" @input.debounce.250ms="search()" @keydown.escape="results = []" @keydown.down.prevent="highlight(1)" @keydown.up.prevent="highlight(-1)"
                               type="text" autocomplete="off" placeholder="Scan barcode, or type a name / SKU"
                               class="form-input py-3 pl-11 font-mono text-lg">
                        {{-- Search results --}}
                        <div x-show="results.length" x-cloak @click.outside="results = []" class="absolute inset-x-0 top-full z-30 mt-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lift">
                            <template x-for="(p, i) in results" :key="p.id">
                                <button type="button" @click="add(p); results = []; input = ''; focusScan()" :class="i === active && 'bg-slate-100'"
                                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-slate-50">
                                    <img :src="p.image_url" alt="" class="h-9 w-9 rounded-lg bg-slate-50 object-contain p-0.5">
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold" x-text="p.name"></span>
                                        <span class="font-mono text-xs text-slate-500" x-text="p.barcode + ' · ' + p.stock + ' in stock'"></span>
                                    </span>
                                    <span class="font-bold" x-text="taka(p.price)"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <button class="btn-dark px-5"><i class="fa-solid fa-plus"></i></button>
                    <button type="button" class="btn-secondary px-4" @click="toggleCamera()" :class="camera && '!bg-slate-900 !text-white'" title="Use camera"><i class="fa-solid fa-camera"></i></button>
                </form>
                <div x-show="camera" x-cloak class="mt-3 overflow-hidden rounded-xl bg-black"><div id="pos-camera"></div></div>
                <p x-show="message" x-cloak x-text="message" class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700"></p>
            </div>

            <div class="card overflow-hidden">
                <div class="card-header">
                    <h2 class="card-title">Basket <span class="font-normal text-slate-400" x-text="'(' + units() + ' items)'"></span></h2>
                    <button type="button" class="btn-ghost btn-sm text-rose-600" x-show="lines.length" @click="clear()"><i class="fa-solid fa-trash-can"></i> Clear</button>
                </div>
                <div class="max-h-[55vh] overflow-y-auto">
                    <table class="table">
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="line in lines" :key="line.id">
                                <tr :class="line.flash && 'bg-emerald-50'">
                                    <td class="w-12"><img :src="line.image_url" alt="" class="h-10 w-10 rounded-lg bg-slate-50 object-contain p-0.5"></td>
                                    <td>
                                        <p class="font-semibold text-slate-900" x-text="line.name"></p>
                                        <p class="text-xs" :class="line.quantity > line.stock ? 'font-semibold text-rose-600' : 'text-slate-500'"
                                           x-text="taka(line.price) + ' each · ' + line.stock + ' in stock'"></p>
                                    </td>
                                    <td class="w-40">
                                        <div class="flex items-center rounded-lg border border-slate-200">
                                            <button type="button" class="px-2.5 py-1.5 text-slate-500 hover:text-slate-900" @click="change(line, -1)"><i class="fa-solid fa-minus text-xs"></i></button>
                                            <input type="number" min="1" max="999" x-model.number="line.quantity" @change="setQty(line, line.quantity)" class="w-14 border-0 p-1 text-center font-semibold focus:ring-0">
                                            <button type="button" class="px-2.5 py-1.5 text-slate-500 hover:text-slate-900" @click="change(line, 1)"><i class="fa-solid fa-plus text-xs"></i></button>
                                        </div>
                                    </td>
                                    <td class="w-28 text-right font-bold" x-text="taka(line.price * line.quantity)"></td>
                                    <td class="w-10"><button type="button" class="text-slate-300 hover:text-rose-600" @click="remove(line)"><i class="fa-solid fa-xmark"></i></button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div x-show="! lines.length" class="px-6 py-16 text-center text-slate-400">
                        <i class="fa-solid fa-basket-shopping mb-3 block text-4xl text-slate-300"></i>
                        Scan the first item to start a sale.
                    </div>
                </div>
            </div>
        </div>

        {{-- Payment --}}
        <div class="space-y-4">
            <div class="card p-5">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd x-text="taka(quote.subtotal)"></dd></div>
                    <div class="flex justify-between text-emerald-700" x-show="quote.discount > 0"><dt x-text="'Discount ' + (quote.coupon ? quote.coupon.code + ' (' + quote.coupon.label + ')' : '')"></dt><dd x-text="'− ' + taka(quote.discount)"></dd></div>
                    <div class="flex items-end justify-between border-t border-slate-100 pt-3">
                        <dt class="text-base font-bold">Total</dt>
                        <dd class="text-4xl font-extrabold tracking-tight text-slate-900" x-text="taka(quote.total)"></dd>
                    </div>
                </dl>
                <p x-show="quoting" class="mt-1 text-right text-xs text-slate-400">updating…</p>
                <template x-for="p in quote.problems"><p class="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700" x-text="p"></p></template>
            </div>

            <div class="card space-y-4 p-5">
                <div>
                    <span class="form-label">Payment method</span>
                    <div class="grid grid-cols-4 gap-2">
                        @foreach (['cash' => 'fa-money-bill-wave', 'card' => 'fa-credit-card', 'bkash' => 'fa-mobile-screen', 'nagad' => 'fa-mobile-screen-button'] as $method => $icon)
                            <button type="button" @click="method = '{{ $method }}'" :class="method === '{{ $method }}' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                                    class="flex flex-col items-center gap-1 rounded-xl border-2 px-1 py-2 text-xs font-semibold transition">
                                <i class="fa-solid {{ $icon }} text-base"></i>{{ \App\Models\Order::PAYMENT_METHODS[$method] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div x-show="method === 'cash'">
                    <label class="form-label" for="tendered">Cash received</label>
                    <input id="tendered" type="number" min="0" step="0.01" x-model.number="tendered" class="form-input text-lg font-bold" placeholder="0">
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <button type="button" class="btn-secondary btn-sm" @click="tendered = quote.total">Exact</button>
                        <template x-for="note in notes()" :key="note">
                            <button type="button" class="btn-secondary btn-sm" @click="tendered = note" x-text="taka(note)"></button>
                        </template>
                    </div>
                    <div class="mt-3 flex items-center justify-between rounded-xl px-4 py-3" :class="tendered >= quote.total && quote.total > 0 ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-50 text-slate-500'">
                        <span class="text-sm font-semibold">Change to give</span>
                        <span class="text-2xl font-extrabold" x-text="tendered >= quote.total ? taka(Math.round((tendered - quote.total) * 100) / 100) : '—'"></span>
                    </div>
                </div>

                <details class="group rounded-xl border border-slate-200 px-4 py-3" :open="coupon !== ''">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-600">Customer & coupon <span class="font-normal text-slate-400">(optional)</span></summary>
                    <div class="mt-3 space-y-3">
                        <input type="text" x-model="customerName" maxlength="120" placeholder="Customer name" class="form-input">
                        <input type="tel" x-model="phone" maxlength="30" placeholder="Phone" class="form-input">
                        <div>
                            <input type="text" x-model="coupon" @input.debounce.400ms="refreshQuote()" maxlength="50" placeholder="Coupon code" class="form-input font-mono uppercase">
                            <p x-show="quote.coupon_error" x-text="quote.coupon_error" class="form-error"></p>
                        </div>
                    </div>
                </details>

                <button type="button" @click="complete()" :disabled="! canComplete()" class="btn-primary w-full py-4 text-lg">
                    <i class="fa-solid" :class="submitting ? 'fa-spinner fa-spin' : 'fa-check'"></i>
                    <span x-text="submitting ? 'Completing…' : 'Complete sale · ' + taka(quote.total)"></span>
                </button>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Your recent sales</h2></div>
                <ul class="divide-y divide-slate-100 text-sm" x-ref="recent">
                    <template x-for="sale in recent" :key="sale.number">
                        <li class="flex items-center gap-3 px-5 py-2.5">
                            <span class="flex-1 font-semibold" x-text="sale.number"></span>
                            <span x-text="taka(sale.total)"></span>
                            <a :href="sale.receipt_url" target="_blank" class="btn-ghost btn-sm" title="Reprint receipt"><i class="fa-solid fa-print"></i></a>
                        </li>
                    </template>
                    <li x-show="! recent.length" class="px-5 py-4 text-slate-400">No sales yet today.</li>
                </ul>
            </div>
        </div>
    </div>

    @push('scripts')
        @include('partials.camera-scanner')
        <script>
            function pos() {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const post = (url, body) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body) });
                const newKey = () => (crypto.randomUUID ? crypto.randomUUID() : Date.now().toString(36) + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2));

                return {
                    input: '', results: [], active: -1, lines: [], message: '', camera: false,
                    method: 'cash', tendered: null, customerName: '', phone: '', coupon: '',
                    quote: { subtotal: 0, discount: 0, total: 0, problems: [], coupon: null, coupon_error: null },
                    quoting: false, submitting: false, key: newKey(), quoteSeq: 0,
                    recent: @js($recent->map(fn ($o) => ['number' => $o->number, 'total' => (float) $o->total_amount, 'receipt_url' => route('pos.receipt', $o)])->values()),

                    init() { this.focusScan(); },
                    taka: (v) => '৳' + Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 }),
                    units() { return this.lines.reduce((n, l) => n + l.quantity, 0); },
                    focusScan() { this.$nextTick(() => this.$refs.scan?.focus()); },
                    notes() { const t = this.quote.total; return [100, 500, 1000, 2000, 5000].map(n => Math.ceil(t / n) * n).filter((v, i, a) => v > t && a.indexOf(v) === i).slice(0, 3); },
                    canComplete() { return this.lines.length && ! this.submitting && ! this.quoting && ! this.quote.problems.length && ! this.quote.coupon_error && (this.method !== 'cash' || (this.tendered ?? 0) >= this.quote.total); },
                    highlight(step) { if (this.results.length) this.active = (this.active + step + this.results.length) % this.results.length; },

                    async scan() {
                        if (this.active >= 0 && this.results[this.active]) { this.add(this.results[this.active]); this.results = []; this.input = ''; return; }
                        let text = this.input.trim(), qty = 1;
                        const m = text.match(/^(\d{1,3})\s*[*xX]\s*(.+)$/);
                        if (m) { qty = parseInt(m[1], 10); text = m[2].trim(); }
                        if (! text) return;
                        this.input = ''; this.results = [];
                        await this.lookup(text, qty);
                    },
                    async lookup(code, qty = 1) {
                        this.message = '';
                        try {
                            const res = await fetch(@js(route('barcodes.lookup')) + '?code=' + encodeURIComponent(code), { headers: { Accept: 'application/json' } });
                            const data = await res.json();
                            if (! res.ok) throw new Error(data.message);
                            if (! data.product.sellable) throw new Error(data.product.name + ' is ' + data.product.status + ' and cannot be sold.');
                            this.add(data.product, qty);
                            ScanSound.ok();
                        } catch (e) { this.message = e.message || 'Lookup failed.'; ScanSound.error(); }
                        this.focusScan();
                    },
                    async search() {
                        const q = this.input.trim(); this.active = -1;
                        if (q.length < 2 || /^\d{6,}$/.test(q) || /^\d+\s*[*xX]/.test(q)) { this.results = []; return; }
                        const res = await fetch(@js(route('barcodes.lookup')) + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
                        if (res.ok && this.input.trim() === q) this.results = (await res.json()).products;
                    },
                    add(p, qty = 1) {
                        const line = this.lines.find(l => l.id === p.id);
                        if (line) { line.quantity = Math.min(999, line.quantity + qty); line.stock = p.stock; this.flash(line); }
                        else { const l = { id: p.id, name: p.name, price: p.price, stock: p.stock, image_url: p.image_url, quantity: Math.min(999, qty), flash: true }; this.lines.unshift(l); this.flash(l); }
                        this.refreshQuote();
                    },
                    flash(line) { line.flash = true; setTimeout(() => line.flash = false, 600); },
                    change(line, d) { this.setQty(line, line.quantity + d); },
                    setQty(line, q) { q = parseInt(q, 10); if (! q || q < 1) return this.remove(line); line.quantity = Math.min(999, q); this.refreshQuote(); },
                    remove(line) { this.lines = this.lines.filter(l => l !== line); this.refreshQuote(); this.focusScan(); },
                    clear() { if (confirm('Clear the basket?')) this.reset(); },
                    reset() {
                        this.lines = []; this.tendered = null; this.customerName = ''; this.phone = ''; this.coupon = ''; this.message = '';
                        this.quote = { subtotal: 0, discount: 0, total: 0, problems: [], coupon: null, coupon_error: null };
                        this.key = newKey(); this.focusScan();
                    },
                    payload() { return this.lines.map(l => ({ product_id: l.id, quantity: l.quantity })); },

                    async refreshQuote() {
                        const seq = ++this.quoteSeq; this.quoting = true;
                        try {
                            const res = await post(@js(route('pos.quote')), { items: this.payload(), coupon: this.coupon || null });
                            const data = await res.json();
                            if (seq !== this.quoteSeq) return; // a newer quote is on its way
                            if (! res.ok) throw new Error(data.message || 'Could not price the basket.');
                            this.quote = data;
                            for (const l of data.lines) { const line = this.lines.find(x => x.id === l.product_id); if (line) { line.price = l.price; line.stock = l.stock; } }
                        } catch (e) { if (seq === this.quoteSeq) this.quote.problems = [e.message]; }
                        finally { if (seq === this.quoteSeq) this.quoting = false; }
                    },

                    async complete() {
                        if (! this.canComplete()) return;
                        this.submitting = true; this.message = '';
                        try {
                            const res = await post(@js(route('pos.store')), {
                                items: this.payload(), payment_method: this.method,
                                amount_tendered: this.method === 'cash' ? this.tendered : null,
                                customer_name: this.customerName || null, phone: this.phone || null,
                                coupon: this.coupon || null, idempotency_key: this.key,
                            });
                            const data = await res.json();
                            if (! res.ok) throw new Error(data.message || 'The sale could not be completed.');
                            ScanSound.ok();
                            this.recent.unshift({ number: data.order.number, total: data.order.total, receipt_url: data.receipt_url });
                            this.recent = this.recent.slice(0, 5);
                            window.open(data.receipt_url + '?print=1', 'receipt', 'width=420,height=720');
                            this.reset();
                        } catch (e) {
                            // Same key on retry: if the sale did go through, the server returns it instead of selling twice.
                            this.message = e.message === 'Failed to fetch' ? 'Network problem — press Complete again; the sale will not be duplicated.' : e.message;
                            ScanSound.error(); this.refreshQuote();
                        } finally { this.submitting = false; }
                    },

                    async toggleCamera() {
                        if (this.camera) { await CameraScanner.stop(); this.camera = false; return; }
                        this.camera = true; await this.$nextTick();
                        try { await CameraScanner.start('pos-camera', (code) => this.lookup(code)); }
                        catch (e) { this.camera = false; this.message = e.message || String(e); }
                    },
                };
            }
        </script>
    @endpush
</x-panel-layout>
