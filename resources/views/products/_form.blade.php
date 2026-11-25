{{-- Shared fields for creating and editing a product. Expects $categories and optional $product. --}}
@php($product = $product ?? null)

<div class="grid gap-6 lg:grid-cols-3" x-data="{ preview: @js($product?->image_url) }">
    <div class="card space-y-5 p-6 lg:col-span-2">
        <div>
            <label for="name" class="form-label">Product name</label>
            <input type="text" id="name" name="name" value="{{ old('name', $product?->name) }}" required maxlength="255" class="form-input">
            @error('name')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description" rows="5" required class="form-input">{{ old('description', $product?->description) }}</textarea>
            @error('description')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="category" class="form-label">Category</label>
                <select id="category" name="category" required class="form-input">
                    <option value="" disabled @selected(! old('category', $product?->category))>Choose a category</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat }}" @selected(old('category', $product?->category) === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
                @error('category')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="date" class="form-label">Listing date</label>
                <input type="date" id="date" name="date" required class="form-input"
                       value="{{ old('date', $product?->date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
                @error('date')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-3" x-data="{ price: @js(old('price', $product?->price)), cost: @js(old('cost_price', $product?->cost_price)) }">
            <div>
                <label for="price" class="form-label">Selling price</label>
                <div class="relative">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-slate-400">৳</span>
                    <input type="number" id="price" name="price" step="0.01" min="0" required class="form-input pl-8" x-model="price">
                </div>
                @error('price')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="cost_price" class="form-label">Cost price <span class="font-normal text-slate-400">(optional)</span></label>
                <div class="relative">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-slate-400">৳</span>
                    <input type="number" id="cost_price" name="cost_price" step="0.01" min="0" class="form-input pl-8" x-model="cost">
                </div>
                @error('cost_price')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <span class="form-label">Margin</span>
                <p class="flex h-[38px] items-center rounded-lg bg-slate-50 px-3 text-sm font-semibold"
                   :class="cost !== null && cost !== '' && +price > 0 ? ((price - cost) / price >= 0 ? 'text-emerald-700' : 'text-rose-600') : 'text-slate-400'"
                   x-text="cost !== null && cost !== '' && +price > 0 ? (((price - cost) / price) * 100).toFixed(1) + '% · ৳' + (price - cost).toFixed(2) + ' per unit' : 'Enter a cost price'"></p>
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="sku" class="form-label">SKU</label>
                <input type="text" id="sku" name="sku" maxlength="64" value="{{ old('sku', $product?->sku) }}" class="form-input font-mono uppercase" placeholder="Generated if empty">
                @error('sku')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="barcode" class="form-label">Barcode (EAN / UPC)</label>
                <input type="text" id="barcode" name="barcode" maxlength="32" inputmode="numeric" value="{{ old('barcode', $product?->barcode) }}" class="form-input font-mono" placeholder="Scan the pack, or leave empty to generate">
                @error('barcode')<p class="form-error">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-slate-500">Scan the manufacturer barcode into this field. Products without one get an in-store code starting with 20.</p>
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            @if (! $product)
                <div>
                    <label for="quantity" class="form-label">Opening stock</label>
                    <input type="number" id="quantity" name="quantity" min="0" required class="form-input" value="{{ old('quantity', 0) }}">
                    @error('quantity')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            @else
                <div>
                    <span class="form-label">Stock</span>
                    <p class="flex h-[38px] items-center justify-between rounded-lg bg-slate-50 px-3 text-sm">
                        <span class="font-semibold">{{ number_format($product->stock_quantity) }} units</span>
                        <a href="{{ route('inventory.index', ['q' => $product->sku]) }}" class="text-xs font-semibold text-brand-600 hover:underline">Receive / adjust</a>
                    </p>
                </div>
            @endif
            <div>
                <label for="reorder_level" class="form-label">Reorder level</label>
                <input type="number" id="reorder_level" name="reorder_level" min="0" max="100000" required class="form-input"
                       value="{{ old('reorder_level', $product?->reorder_level ?? setting('default_reorder_level')) }}">
                <p class="mt-1 text-xs text-slate-500">Flag the product as low when stock falls to this level.</p>
                @error('reorder_level')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product?->is_active ?? true)) class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            <span>
                <span class="block text-sm font-semibold text-slate-800">Active — available in the shop and at the till</span>
                <span class="text-xs text-slate-500">Untick to pause selling without deleting the product.</span>
            </span>
        </label>
    </div>

    <div class="card p-6">
        <span class="form-label">Product image</span>
        <label for="image" class="mt-1 flex aspect-square cursor-pointer flex-col items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 transition hover:border-brand-400">
            <template x-if="preview"><img :src="preview" alt="" class="h-full w-full object-contain p-3"></template>
            <template x-if="!preview">
                <span class="text-center text-sm text-slate-500">
                    <i class="fa-solid fa-cloud-arrow-up mb-2 block text-3xl text-slate-300"></i>
                    Click to upload<br><span class="text-xs text-slate-400">PNG, JPG or WebP up to 4 MB</span>
                </span>
            </template>
        </label>
        <input type="file" id="image" name="image" accept="image/*" class="sr-only"
               @change="const f = $event.target.files[0]; if (f) preview = URL.createObjectURL(f)">
        @error('image')<p class="form-error">{{ $message }}</p>@enderror
        @if ($product)
            <p class="mt-2 text-xs text-slate-500">Leave empty to keep the current image.</p>
            <div class="mt-5 border-t border-slate-100 pt-4 text-center">
                <span class="form-label">Barcode</span>
                <img src="{{ route('barcodes.image', $product) }}?v={{ $product->updated_at?->timestamp }}" alt="{{ $product->barcode }}" class="mx-auto h-14">
                <p class="mt-1 font-mono text-xs text-slate-500">{{ $product->barcode }}</p>
                <a href="{{ route('barcodes.labels', ['ids' => [$product->id]]) }}" class="btn-secondary btn-sm mt-3"><i class="fa-solid fa-tags"></i> Print labels</a>
            </div>
        @endif
    </div>
</div>
