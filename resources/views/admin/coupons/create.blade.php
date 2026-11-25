<x-panel-layout title="New coupon" subtitle="Codes are case-insensitive and saved in uppercase">
    <x-slot:actions>
        <a href="{{ route('admin.coupons') }}" class="btn-secondary"><i class="fa-solid fa-arrow-left"></i> All coupons</a>
    </x-slot:actions>

    <form method="POST" action="{{ route('admin.coupons.store') }}" class="card max-w-2xl p-6">
        @csrf
        <div class="space-y-5">
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="name" class="form-label">Coupon code</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="50" placeholder="e.g. EID25"
                           class="form-input font-mono uppercase tracking-wider">
                    @error('name')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="discount" class="form-label">Discount (%)</label>
                    <div class="relative">
                        <input type="number" id="discount" name="discount" value="{{ old('discount', 10) }}" min="1" max="100" step="0.5" required class="form-input pr-8">
                        <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">%</span>
                    </div>
                    @error('discount')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="expiry_date" class="form-label">Expires at</label>
                <input type="datetime-local" id="expiry_date" name="expiry_date" required class="form-input sm:w-1/2"
                       value="{{ old('expiry_date', now()->addMonth()->format('Y-m-d\TH:i')) }}" min="{{ now()->format('Y-m-d\TH:i') }}">
                @error('expiry_date')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <fieldset class="rounded-xl border border-slate-200 p-4">
                <legend class="px-1 text-sm font-semibold text-slate-700">Limits <span class="font-normal text-slate-400">(leave empty for no limit)</span></legend>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="min_order_amount" class="form-label">Minimum order (৳)</label>
                        <input type="number" id="min_order_amount" name="min_order_amount" value="{{ old('min_order_amount') }}" min="0" step="0.01" class="form-input">
                        @error('min_order_amount')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="max_uses" class="form-label">Total uses</label>
                        <input type="number" id="max_uses" name="max_uses" value="{{ old('max_uses') }}" min="1" class="form-input">
                        @error('max_uses')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="max_uses_per_customer" class="form-label">Uses per customer</label>
                        <input type="number" id="max_uses_per_customer" name="max_uses_per_customer" value="{{ old('max_uses_per_customer') }}" min="1" class="form-input">
                        @error('max_uses_per_customer')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <p class="mt-3 text-xs text-slate-500">Limits are enforced even when many customers check out at the same moment. A cancelled order gives its use back.</p>
            </fieldset>
        </div>
        <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-5">
            <a href="{{ route('admin.coupons') }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary"><i class="fa-solid fa-ticket"></i> Create coupon</button>
        </div>
    </form>
</x-panel-layout>
