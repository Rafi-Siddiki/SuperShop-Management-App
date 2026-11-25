@props(['label', 'value', 'icon', 'tone' => 'brand', 'change' => null, 'hint' => null, 'href' => null])

@php
    $tones = [
        'brand' => 'from-brand-500 to-accent-500',
        'emerald' => 'from-emerald-500 to-teal-500',
        'sky' => 'from-sky-500 to-indigo-500',
        'amber' => 'from-amber-400 to-orange-500',
        'violet' => 'from-violet-500 to-fuchsia-500',
        'rose' => 'from-rose-500 to-pink-500',
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'card group block p-5 transition' . ($href ? ' hover:-translate-y-0.5 hover:shadow-lift' : '')]) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
            <p class="mt-1.5 truncate text-2xl font-extrabold tracking-tight text-slate-900">{{ $value }}</p>
        </div>
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br {{ $tones[$tone] ?? $tones['brand'] }} text-white shadow-sm">
            <i class="fa-solid {{ $icon }}"></i>
        </span>
    </div>
    @if (! is_null($change) || $hint)
        <p class="mt-3 flex items-center gap-2 text-xs">
            @if (! is_null($change))
                <span @class([
                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-bold',
                    'bg-emerald-50 text-emerald-700' => $change >= 0,
                    'bg-rose-50 text-rose-700' => $change < 0,
                ])>
                    <i class="fa-solid {{ $change >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }}"></i>{{ abs($change) }}%
                </span>
            @endif
            @if ($hint)
                <span class="text-slate-500">{{ $hint }}</span>
            @endif
        </p>
    @endif
</{{ $tag }}>
