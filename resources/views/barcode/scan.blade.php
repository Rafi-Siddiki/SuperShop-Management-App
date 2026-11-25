@php($isAdmin = auth()->user()->isAdmin())

<x-panel-layout title="Scan barcode" subtitle="Look up any product with a scanner, the camera or by typing its barcode / SKU">
    <div x-data="scanPage()" x-init="focus()" class="grid gap-6 xl:grid-cols-5">
        <div class="space-y-6 xl:col-span-3">
            <div class="card p-5">
                <form @submit.prevent="lookup(code)" class="flex gap-2">
                    <div class="relative flex-1">
                        <i class="fa-solid fa-barcode pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-lg text-slate-400"></i>
                        <input x-ref="input" x-model="code" type="text" inputmode="numeric" autocomplete="off" placeholder="Scan or type a barcode / SKU and press Enter"
                               class="form-input py-3 pl-11 font-mono text-lg tracking-wider">
                    </div>
                    <button class="btn-dark px-5" :disabled="busy"><i class="fa-solid fa-magnifying-glass"></i></button>
                    <button type="button" class="btn-secondary px-4" @click="toggleCamera()" :class="camera && '!bg-slate-900 !text-white'" title="Use camera">
                        <i class="fa-solid fa-camera"></i>
                    </button>
                </form>
                <div x-show="camera" x-cloak class="mt-4 overflow-hidden rounded-xl bg-black"><div id="camera-reader"></div></div>
                <p x-show="error" x-cloak x-text="error" class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700"></p>
            </div>

            {{-- Result --}}
            <template x-if="product">
                <div class="card overflow-hidden">
                    <div class="flex flex-col gap-5 p-5 sm:flex-row">
                        <img :src="product.image_url" alt="" class="h-40 w-40 shrink-0 self-center rounded-xl bg-slate-50 object-contain p-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="badge bg-slate-100 text-slate-600 ring-slate-500/10" x-text="product.category"></span>
                                <span x-show="product.status !== 'active'" class="badge bg-rose-50 text-rose-700 ring-rose-600/20" x-text="product.status === 'deleted' ? 'Deleted' : 'Inactive — not for sale'"></span>
                            </div>
                            <h2 class="mt-2 text-xl font-extrabold text-slate-900" x-text="product.name"></h2>
                            <p class="font-mono text-sm text-slate-500"><span x-text="product.sku"></span> · <span x-text="product.barcode"></span></p>
                            <div class="mt-4 grid grid-cols-3 gap-3">
                                <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-500">Price</p><p class="text-lg font-extrabold" x-text="taka(product.price)"></p></div>
                                <div class="rounded-xl p-3" :class="product.stock <= 0 ? 'bg-rose-50' : (product.stock <= product.reorder_level ? 'bg-amber-50' : 'bg-emerald-50')">
                                    <p class="text-xs text-slate-500">In stock</p><p class="text-lg font-extrabold" x-text="product.stock"></p>
                                </div>
                                <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-500">Reorder at</p><p class="text-lg font-extrabold" x-text="product.reorder_level"></p></div>
                            </div>
                            <template x-if="product.cost_price !== null">
                                <p class="mt-2 text-xs text-slate-500">Cost <span x-text="taka(product.cost_price)"></span> · margin <span x-text="product.price > 0 ? (((product.price - product.cost_price) / product.price) * 100).toFixed(1) + '%' : '—'"></span></p>
                            </template>
                            <img :src="product.barcode_url" alt="" class="mt-4 h-14">
                        </div>
                    </div>

                    <div class="flex flex-wrap items-end gap-2 border-t border-slate-100 bg-slate-50/60 p-4">
                        <form method="POST" :action="receiveUrl.replace('__ID__', product.id)" class="flex items-end gap-2" x-show="product.status !== 'deleted'">
                            @csrf
                            <div>
                                <label class="form-label text-xs">Receive delivery</label>
                                <input type="number" name="quantity" min="1" max="100000" required placeholder="Qty" class="form-input w-24 py-1.5">
                            </div>
                            <input type="hidden" name="note" value="Received via scanner">
                            <button class="btn-primary btn-sm py-2"><i class="fa-solid fa-truck-ramp-box"></i> Add to stock</button>
                        </form>
                        <div class="ml-auto flex flex-wrap gap-2">
                            <a :href="product.history_url" class="btn-secondary btn-sm"><i class="fa-solid fa-clock-rotate-left"></i> History</a>
                            <a :href="labelsUrl + '?ids[]=' + product.id" class="btn-secondary btn-sm"><i class="fa-solid fa-tags"></i> Print labels</a>
                            <a x-show="product.edit_url" :href="product.edit_url" class="btn-secondary btn-sm"><i class="fa-solid fa-pen"></i> Edit</a>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="xl:col-span-2">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Recent scans</h2><button class="btn-ghost btn-sm" x-show="history.length" @click="history = []">Clear</button></div>
                <ul class="divide-y divide-slate-100">
                    <template x-for="item in history" :key="item.at">
                        <li class="flex cursor-pointer items-center gap-3 px-5 py-2.5 hover:bg-slate-50" @click="product = item.product">
                            <img :src="item.product.image_url" alt="" class="h-9 w-9 rounded-lg bg-slate-50 object-contain p-0.5">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold" x-text="item.product.name"></p>
                                <p class="font-mono text-xs text-slate-500" x-text="item.product.barcode"></p>
                            </div>
                            <span class="text-xs text-slate-400" x-text="item.time"></span>
                        </li>
                    </template>
                    <li x-show="! history.length" class="px-5 py-10 text-center text-sm text-slate-400">
                        <i class="fa-solid fa-barcode mb-2 block text-3xl text-slate-300"></i>
                        Scanned products appear here. A USB scanner works straight away — just scan.
                    </li>
                </ul>
            </div>
        </div>
    </div>

    @push('scripts')
        @include('partials.camera-scanner')
        <script>
            function scanPage() {
                return {
                    code: '', product: null, error: '', busy: false, camera: false, history: [],
                    receiveUrl: @js(route('inventory.receive', '__ID__')),
                    labelsUrl: @js(route('barcodes.labels')),
                    taka: (v) => '৳' + Number(v).toLocaleString('en-IN', { maximumFractionDigits: 2 }),
                    focus() { this.$nextTick(() => this.$refs.input?.focus()); },
                    async lookup(code) {
                        code = (code || '').trim();
                        if (! code || this.busy) return;
                        this.busy = true; this.error = '';
                        try {
                            const res = await fetch(@js(route('barcodes.lookup')) + '?code=' + encodeURIComponent(code), { headers: { Accept: 'application/json' } });
                            const data = await res.json();
                            if (! res.ok) throw new Error(data.message || 'Lookup failed.');
                            this.product = data.product;
                            this.history.unshift({ product: data.product, at: Date.now() + Math.random(), time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) });
                            this.history = this.history.slice(0, 15);
                            ScanSound.ok();
                        } catch (e) {
                            this.error = e.message; ScanSound.error();
                        } finally {
                            this.busy = false; this.code = ''; this.focus();
                        }
                    },
                    async toggleCamera() {
                        if (this.camera) { await CameraScanner.stop(); this.camera = false; return; }
                        this.camera = true; this.error = '';
                        await this.$nextTick();
                        try { await CameraScanner.start('camera-reader', (code) => this.lookup(code)); }
                        catch (e) { this.camera = false; this.error = e.message || String(e); }
                    },
                };
            }
        </script>
    @endpush
</x-panel-layout>
