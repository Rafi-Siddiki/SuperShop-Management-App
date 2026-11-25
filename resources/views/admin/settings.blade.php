<x-panel-layout title="Settings" subtitle="Store details, delivery charges, VAT and receipts">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-4xl space-y-6">
        @csrf
        @method('PUT')

        @php
            $field = function (string $key, string $label, string $type = 'text', ?string $hint = null, array $attrs = []) use ($settings) {
                return compact('key', 'label', 'type', 'hint', 'attrs') + ['value' => old($key, $settings[$key])];
            };
            $groups = [
                ['Store details', 'fa-store', 'Shown in the shop header and footer, on invoices, receipts and reports.', [
                    $field('store_name', 'Store name'),
                    $field('store_phone', 'Phone'),
                    $field('store_email', 'Email', 'email'),
                    $field('store_hours', 'Opening hours'),
                    $field('store_address', 'Address', 'text', null, ['class' => 'sm:col-span-2']),
                    $field('store_tagline', 'Tagline', 'text', null, ['class' => 'sm:col-span-2']),
                ]],
                ['Delivery', 'fa-truck-fast', 'Charged on online orders. The till never charges delivery.', [
                    $field('delivery_fee', 'Delivery fee (৳)', 'number', null, ['step' => '0.01', 'min' => 0]),
                    $field('free_delivery_threshold', 'Free delivery from (৳)', 'number', 'Order value after discounts. 0 = never free.', ['step' => '0.01', 'min' => 0]),
                ]],
                ['Tax & receipts', 'fa-receipt', 'Prices are VAT-inclusive; the VAT part is printed on receipts and invoices.', [
                    $field('vat_rate', 'VAT rate (%)', 'number', '0 to hide VAT lines.', ['step' => '0.01', 'min' => 0, 'max' => 50]),
                    $field('bin_number', 'BIN (VAT registration no.)'),
                    $field('receipt_footer', 'Receipt footer', 'text', null, ['class' => 'sm:col-span-2']),
                ]],
                ['Inventory & returns', 'fa-boxes-stacked', null, [
                    $field('default_reorder_level', 'Default reorder level', 'number', 'Used for new products created by CSV import.', ['min' => 0]),
                    $field('return_window_days', 'Return window (days)', 'number', 'Staff are warned when recording a return after this.', ['min' => 0]),
                ]],
            ];
        @endphp

        @foreach ($groups as [$title, $icon, $description, $fields])
            <div class="card p-6">
                <div class="mb-5 flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><i class="fa-solid {{ $icon }}"></i></span>
                    <div>
                        <h2 class="card-title">{{ $title }}</h2>
                        @if ($description)<p class="text-sm text-slate-500">{{ $description }}</p>@endif
                    </div>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach ($fields as $f)
                        <div class="{{ $f['attrs']['class'] ?? '' }}">
                            <label for="{{ $f['key'] }}" class="form-label">{{ $f['label'] }}</label>
                            <input id="{{ $f['key'] }}" name="{{ $f['key'] }}" type="{{ $f['type'] }}" value="{{ $f['value'] }}" class="form-input"
                                   @foreach (\Illuminate\Support\Arr::except($f['attrs'], 'class') as $attr => $val) {{ $attr }}="{{ $val }}" @endforeach>
                            @if ($f['hint'])<p class="mt-1 text-xs text-slate-500">{{ $f['hint'] }}</p>@endif
                            @error($f['key'])<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="flex justify-end">
            <button class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save settings</button>
        </div>
    </form>
</x-panel-layout>
