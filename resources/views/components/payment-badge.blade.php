@props(['status'])

<span {{ $attributes->merge(['class' => 'badge capitalize ' . (\App\Models\Order::PAYMENT_STYLES[$status] ?? 'bg-slate-50 text-slate-600 ring-slate-500/20')]) }}>
    {{ $status }}
</span>
