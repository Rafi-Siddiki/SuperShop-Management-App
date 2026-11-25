<x-panel-layout title="Import & export products" subtitle="Update prices, barcodes and stock in bulk with a spreadsheet">
    <x-slot:actions>
        <a href="{{ route('products.index') }}" class="btn-secondary"><i class="fa-solid fa-arrow-left"></i> Products</a>
    </x-slot:actions>

    @if (session('import_errors'))
        <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            <p class="font-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Nothing was imported. Please fix these rows:</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach (session('import_errors') as $problem)
                    <li>{{ $problem }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card p-6">
            <h2 class="card-title"><i class="fa-solid fa-file-arrow-down mr-1.5 text-slate-400"></i> Export</h2>
            <p class="mt-1 text-sm text-slate-500">Download every product as a CSV file. Edit it in Excel or Google Sheets and import it back.</p>
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('products.export') }}" class="btn-dark"><i class="fa-solid fa-file-csv"></i> Export products</a>
                <a href="{{ route('inventory.export') }}" class="btn-secondary"><i class="fa-solid fa-boxes-stacked"></i> Inventory valuation</a>
            </div>
        </div>

        <form method="POST" action="{{ route('products.import.store') }}" enctype="multipart/form-data" class="card p-6" x-data="{ busy: false }" @submit="busy = true">
            @csrf
            <h2 class="card-title"><i class="fa-solid fa-file-arrow-up mr-1.5 text-slate-400"></i> Import</h2>
            <p class="mt-1 text-sm text-slate-500">Rows are matched to existing products by SKU, then by barcode. Unmatched rows create new products. Every row is checked first: if any row has a problem, nothing is imported.</p>
            <input type="file" name="file" accept=".csv,text/csv" required class="mt-5 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-700">
            @error('file')<p class="form-error">{{ $message }}</p>@enderror
            <div class="mt-5 flex flex-wrap gap-2">
                <button class="btn-primary" :disabled="busy"><i class="fa-solid" :class="busy ? 'fa-spinner fa-spin' : 'fa-upload'"></i> Import</button>
                <a href="{{ route('products.import.template') }}" class="btn-ghost"><i class="fa-solid fa-download"></i> Template</a>
            </div>
        </form>
    </div>

    <div class="card mt-6 overflow-hidden">
        <div class="card-header"><h2 class="card-title">Columns</h2></div>
        <table class="table">
            <thead><tr><th>Column</th><th>Required</th><th>Notes</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                <tr><td class="font-mono">sku</td><td>no</td><td>Letters, digits and - _ . /. Generated when empty for new products.</td></tr>
                <tr><td class="font-mono">barcode</td><td>no</td><td>EAN-8, UPC-A, EAN-13 or GTIN-14 with a valid check digit. Generated when empty. Excel may show long numbers as 8.9E+12 — format the column as Text.</td></tr>
                <tr><td class="font-mono">name</td><td>yes</td><td></td></tr>
                <tr><td class="font-mono">category</td><td>yes</td><td>{{ implode(', ', $categories) }}</td></tr>
                <tr><td class="font-mono">price</td><td>yes</td><td>Selling price in taka, e.g. 1199 or 99.50</td></tr>
                <tr><td class="font-mono">cost_price</td><td>no</td><td>Purchase cost per unit, used for profit and stock valuation.</td></tr>
                <tr><td class="font-mono">reorder_level</td><td>no</td><td>Alert when stock falls to this level. Default {{ setting('default_reorder_level') }}.</td></tr>
                <tr><td class="font-mono">active</td><td>no</td><td>yes / no — inactive products are hidden from the shop and the till.</td></tr>
                <tr><td class="font-mono">stock</td><td>no</td><td>Quantity on hand. A difference is recorded as a stock count in the movement log. Leave empty to keep stock unchanged.</td></tr>
                <tr><td class="font-mono">description</td><td>no</td><td>Defaults to the name for new products.</td></tr>
            </tbody>
        </table>
    </div>
</x-panel-layout>
