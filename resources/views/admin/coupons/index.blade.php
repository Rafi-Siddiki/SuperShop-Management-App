<x-panel-layout title="Coupons" subtitle="Discount codes for the online shop and the till">
    <x-slot:actions>
        <a href="{{ route('admin.coupons.create') }}" class="btn-primary"><i class="fa-solid fa-plus"></i> New coupon</a>
    </x-slot:actions>

    @if ($coupons->isEmpty())
        <div class="card"><x-empty-state icon="fa-ticket" title="No coupons yet" message="Create a discount code to run a promotion.">
            <a href="{{ route('admin.coupons.create') }}" class="btn-primary"><i class="fa-solid fa-plus"></i> New coupon</a>
        </x-empty-state></div>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($coupons as $coupon)
                @php
                    $expired = $coupon->isExpired();
                    $usedUp = $coupon->max_uses !== null && $coupon->uses_count >= $coupon->max_uses;
                    $live = $coupon->is_active && ! $expired && ! $usedUp;
                    $state = ! $coupon->is_active ? 'Paused' : ($expired ? 'Expired' : ($usedUp ? 'Used up' : 'Live'));
                @endphp
                <div @class(['card relative overflow-hidden', 'opacity-75' => ! $live])>
                    <div @class(['flex items-center justify-between gap-3 px-5 py-4 text-white', 'bg-gradient-to-r from-brand-600 to-accent-500' => $live, 'bg-slate-400' => ! $live])>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest text-white/80">{{ $state }}</p>
                            <p class="font-mono text-xl font-extrabold tracking-wider">{{ $coupon->name }}</p>
                        </div>
                        <p class="text-3xl font-extrabold">{{ rtrim(rtrim($coupon->discount, '0'), '.') }}<span class="text-lg">% off</span></p>
                    </div>
                    <div class="space-y-1.5 px-5 py-4 text-sm text-slate-500">
                        <p><i class="fa-regular fa-clock mr-1 w-4"></i>{{ $expired ? 'Expired' : 'Expires' }} {{ $coupon->expiry_date->format('d M Y, h:i A') }} <span class="text-slate-400">({{ $coupon->expiry_date->diffForHumans() }})</span></p>
                        <p><i class="fa-solid fa-receipt mr-1 w-4"></i>Used {{ $coupon->uses_count }}{{ $coupon->max_uses ? ' / ' . $coupon->max_uses : '' }} times · {{ money($coupon->discount_given ?? 0) }} given</p>
                        @if ($coupon->min_order_amount || $coupon->max_uses_per_customer)
                            <p><i class="fa-solid fa-sliders mr-1 w-4"></i>
                                @if ($coupon->min_order_amount) Min. order {{ money($coupon->min_order_amount) }} @endif
                                @if ($coupon->min_order_amount && $coupon->max_uses_per_customer) · @endif
                                @if ($coupon->max_uses_per_customer) {{ $coupon->max_uses_per_customer }}× per customer @endif
                            </p>
                        @endif
                    </div>
                    <div class="flex justify-end gap-1 border-t border-slate-100 px-3 py-2">
                        <form method="POST" action="{{ route('admin.coupons.toggle', $coupon) }}">
                            @csrf @method('PATCH')
                            <button class="btn-ghost btn-sm"><i class="fa-solid {{ $coupon->is_active ? 'fa-pause' : 'fa-play' }}"></i> {{ $coupon->is_active ? 'Pause' : 'Activate' }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" onsubmit="return confirm(@js('Delete coupon ' . $coupon->name . '?'))">
                            @csrf @method('DELETE')
                            <button class="btn-ghost btn-sm text-rose-600 hover:bg-rose-50 hover:text-rose-700"><i class="fa-solid fa-trash"></i> Delete</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-panel-layout>
